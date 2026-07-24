<?php

declare(strict_types=1);

namespace App\Services\Central\Tenants;

use App\Enums\Central\PaymentGateway;
use App\Enums\Central\SignupIntentStatus;
use App\Models\Central\Plan;
use App\Models\Central\SignupIntent;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Payments\PaymentMethodPayload;
use App\Payments\SetupSessionResult;
use App\Services\Central\Billing\CardVerificationService;
use App\Services\Central\Billing\PaymentGatewayResolver;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Two-step public signup with soft card verification before tenant creation.
 */
final class SignupIntentService
{
    public function __construct(
        private readonly TenantSignupService $tenantSignupService,
        private readonly CardVerificationService $cardVerification,
        private readonly PaymentGatewayResolver $gatewayResolver,
        private readonly TenantSettings $tenantSettings,
    ) {}

    /**
     * Resolve signup currency and customer-facing gateways for a country.
     *
     * @return array{currency: string, gateways: list<array{value: string, label: string, recommended: bool}>}
     */
    public function paymentOptions(string $country): array
    {
        $country = Str::upper(trim($country));
        $currency = $this->cardVerification->resolveCurrency($country);

        return [
            'currency' => $currency,
            'gateways' => $this->gatewayResolver->optionsForCurrency($currency, $country),
        ];
    }

    /**
     * Validate signup data and start a provider card setup session.
     *
     * @param  array<string, mixed>  $data
     * @return array{signup_intent_id: string, checkout_url: string, gateway: string, currency: string, expires_at: string}
     *
     * @throws ValidationException|Throwable
     */
    public function setup(array $data): array
    {
        $this->assertPlanIsPublic((int) $data['plan_id']);

        $country = Str::upper(trim((string) $data['country']));
        $currency = $this->cardVerification->resolveCurrency($country);
        $available = collect($this->gatewayResolver->optionsForCurrency($currency, $country))
            ->pluck('value')
            ->all();
        $gateway = Str::lower(trim((string) ($data['gateway'] ?? '')));

        if ($gateway === '' || ! in_array($gateway, $available, true)) {
            throw ValidationException::withMessages([
                'gateway' => ['Select a payment provider that supports this currency.'],
            ]);
        }

        $amount = $this->cardVerification->verificationAmount($currency, $gateway);

        $intent = SignupIntent::query()->create([
            'status' => SignupIntentStatus::Pending,
            'email' => (string) $data['email'],
            'gateway' => PaymentGateway::from($gateway),
            'currency' => $currency,
            'verification_amount' => $amount,
            'payload' => Arr::except($data, ['password', 'password_confirmation']),
            'password_secret' => (string) $data['password'],
            'expires_at' => now()->addHours($this->tenantSettings->signupIntentTtlHours()),
        ]);

        $frontendBase = rtrim((string) config('billing.frontend_url'), '/');
        $completePath = str_replace(
            '{intent}',
            $intent->id,
            (string) config('billing.frontend_signup_complete_path', '/central/signup/complete/{intent}'),
        );
        $cancelPath = (string) config('billing.frontend_signup_cancel_path', '/central/signup');

        $successUrl = $frontendBase.$completePath;
        if ($gateway === 'stripe') {
            $successUrl .= (str_contains($completePath, '?') ? '&' : '?').'session_id={CHECKOUT_SESSION_ID}';
        }

        $cancelUrl = $frontendBase.$cancelPath.(str_contains($cancelPath, '?') ? '&' : '?').'cancelled=1';

        $session = $this->cardVerification->startSetup($gateway, [
            'email' => (string) $data['email'],
            'currency' => $currency,
            'amount' => $amount,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'reference' => 'SIGNUP_'.Str::upper(Str::replace('-', '', $intent->id)),
            'metadata' => [
                'signup_intent_id' => $intent->id,
                'name' => (string) ($data['owner_name'] ?? $data['name']),
            ],
        ]);

        if (! $session->successful || blank($session->checkoutUrl)) {
            $intent->update([
                'status' => SignupIntentStatus::Failed,
                'password_secret' => null,
            ]);

            throw ValidationException::withMessages([
                'payment' => [$session->message ?? 'Unable to start card verification.'],
            ]);
        }

        $intent->update([
            'gateway_reference' => $session->reference,
            'checkout_url' => $session->checkoutUrl,
            'verification_meta' => $session->raw,
        ]);

        return [
            'signup_intent_id' => $intent->id,
            'checkout_url' => $session->checkoutUrl,
            'gateway' => $gateway,
            'currency' => $currency,
            'expires_at' => $intent->expires_at->toIso8601String(),
        ];
    }

    private function assertPlanIsPublic(int $planId): void
    {
        $plan = Plan::query()->findOrFail($planId);

        if (! $plan->isPubliclyVisible()) {
            throw ValidationException::withMessages([
                'plan_id' => ['The selected plan is not available for self-serve signup.'],
            ]);
        }
    }

    /**
     * Confirm provider setup and create the tenant when verification succeeds.
     *
     * @param  array<string, mixed>  $providerParams
     * @return array{tenant: Tenant, subscription: Subscription}
     *
     * @throws ValidationException|Throwable
     */
    public function complete(string $intentId, array $providerParams = []): array
    {
        try {
            return Cache::lock("signup-intent:complete:{$intentId}", 900)
                ->block(5, fn (): array => $this->completeLocked($intentId, $providerParams));
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'signup_intent_id' => ['This signup session is already being completed. Please try again shortly.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $providerParams
     * @return array{tenant: Tenant, subscription: Subscription}
     */
    private function completeLocked(string $intentId, array $providerParams): array
    {
        $intent = SignupIntent::query()->findOrFail($intentId);

        if ($intent->status === SignupIntentStatus::Completed && filled($intent->tenant_id)) {
            $tenant = $intent->tenant()->with(['domains'])->withCount(['domains', 'notes'])->firstOrFail();
            $subscription = $tenant->subscriptions()->with(['plan', 'planPrice', 'invoices'])->latest('id')->firstOrFail();

            return [
                'tenant' => $tenant,
                'subscription' => $subscription,
            ];
        }

        if ($intent->isExpired()) {
            $intent->update([
                'status' => SignupIntentStatus::Expired,
                'payload' => $this->sanitizedPayload($intent->payload),
                'password_secret' => null,
            ]);

            throw ValidationException::withMessages([
                'signup_intent_id' => ['This signup session has expired. Please start again.'],
            ]);
        }

        if (! in_array($intent->status, [SignupIntentStatus::Pending, SignupIntentStatus::Verified], true)) {
            throw ValidationException::withMessages([
                'signup_intent_id' => ['This signup session cannot be completed.'],
            ]);
        }

        $reference = (string) (
            $providerParams['session_id']
            ?? $providerParams['trxref']
            ?? $providerParams['reference']
            ?? $intent->gateway_reference
            ?? ''
        );

        if ($reference === '') {
            throw ValidationException::withMessages([
                'payment' => ['Missing payment verification reference.'],
            ]);
        }

        $confirmed = $this->cardVerification->confirmSetup($intent->gateway->value, $reference, [
            'currency' => $intent->currency,
            'transaction_id' => $providerParams['transaction_id'] ?? $providerParams['id'] ?? null,
            'refund' => $this->cardVerification->shouldRefund(),
        ]);

        if ($confirmed instanceof SetupSessionResult) {
            $intent->update([
                'status' => SignupIntentStatus::Failed,
                'payload' => $this->sanitizedPayload($intent->payload),
                'password_secret' => null,
            ]);

            throw ValidationException::withMessages([
                'payment' => [$confirmed->message ?? 'Card verification failed.'],
            ]);
        }

        /** @var PaymentMethodPayload $confirmed */
        $this->cardVerification->refundVerificationIfNeeded($confirmed);

        $intent->update([
            'status' => SignupIntentStatus::Verified,
            'verified_at' => now(),
            'verification_meta' => array_merge($intent->verification_meta ?? [], [
                'payment_method' => [
                    'gateway' => $confirmed->gateway,
                    'brand' => $confirmed->brand,
                    'last_four' => $confirmed->lastFour,
                ],
            ]),
        ]);

        $signupData = $this->sanitizedPayload($intent->payload);
        $password = $intent->password_secret ?? ($intent->payload['password'] ?? null);

        if (! is_string($password) || $password === '') {
            throw ValidationException::withMessages([
                'signup_intent_id' => ['This signup session no longer contains valid owner credentials. Please start again.'],
            ]);
        }

        $signupData['password'] = $password;
        $result = $this->tenantSignupService->signup($signupData);

        $paymentMethod = $this->cardVerification->storePaymentMethod($result['tenant'], $confirmed, true);

        $result['subscription']->update([
            'default_payment_method_id' => $paymentMethod->id,
        ]);

        $intent->update([
            'status' => SignupIntentStatus::Completed,
            'completed_at' => now(),
            'tenant_id' => $result['tenant']->id,
            'payload' => $this->sanitizedPayload($intent->payload),
            'password_secret' => null,
        ]);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizedPayload(array $payload): array
    {
        return Arr::except($payload, ['password', 'password_confirmation']);
    }
}
