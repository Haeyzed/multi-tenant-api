<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Enums\Central\PaymentGateway;
use App\Enums\Central\PaymentMethodStatus;
use App\Models\Central\Payment;
use App\Models\Central\PaymentMethod;
use App\Models\Central\Tenant;
use App\Payments\PaymentGatewayManager;
use App\Payments\PaymentMethodPayload;
use App\Payments\SetupSessionResult;
use App\Services\Central\Settings\SettingService;
use App\Services\Central\World\WorldService;
use Illuminate\Support\Str;

/**
 * Soft card verification helpers for signup and payment-method vaulting.
 */
final class CardVerificationService
{
    public function __construct(
        private readonly SettingService         $settings,
        private readonly PaymentGatewayResolver $gatewayResolver,
        private readonly PaymentGatewayManager  $gateways,
        private readonly WorldService           $world,
        private readonly PaymentSettingsPolicy  $paymentSettingsPolicy,
    )
    {
    }

    public function isRequired(): bool
    {
        return (bool)$this->settings->get('billing.signup_card_verification', true);
    }

    public function verificationAmount(string $currency, string $gateway): float
    {
        if ($gateway === PaymentGateway::STRIPE->value) {
            return 0.0;
        }

        $fallback = max(0.0, (float)$this->settings->get('billing.card_verification_amount', 50.0));
        $amount = $this->paymentSettingsPolicy->verificationAmount(
            $this->settings->get('billing.card_verification_amounts'),
            $currency,
            $fallback,
        );
        $minimum = $this->paymentSettingsPolicy->verificationMinimum(
            $this->settings->get('billing.card_verification_minimums'),
            $gateway,
            $currency,
        );

        return max($amount, $minimum);
    }

    public function resolveCurrency(?string $countryIso2, ?string $fallback = null): string
    {
        $override = $this->settings->get('billing.card_verification_currency');

        if (is_string($override) && strlen(trim($override)) === 3) {
            return Str::upper(trim($override));
        }

        if (filled($countryIso2)) {
            $fromCountry = $this->world->currencyForCountry(Str::upper($countryIso2));

            if (filled($fromCountry)) {
                return Str::upper((string)$fromCountry);
            }
        }

        return Str::upper((string)($fallback ?: $this->settings->get('billing.default_currency', config('payments.currency', 'USD'))));
    }

    public function resolveGateway(string $currency): string
    {
        return $this->gatewayResolver->resolve($currency);
    }

    /**
     * @param array{
     *     email: string,
     *     currency: string,
     *     amount: float,
     *     success_url: string,
     *     cancel_url: string,
     *     reference?: string,
     *     metadata?: array<string, mixed>
     * } $options
     */
    public function startSetup(string $gateway, array $options): SetupSessionResult
    {
        return $this->gateways->driver($gateway)->createSetupSession($options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function confirmSetup(string $gateway, string $reference, array $options = []): PaymentMethodPayload|SetupSessionResult
    {
        $options['refund'] = $this->shouldRefund();

        return $this->gateways->driver($gateway)->confirmSetup($reference, $options);
    }

    public function shouldRefund(): bool
    {
        return (bool)$this->settings->get('billing.card_verification_refund', true);
    }

    public function storePaymentMethod(Tenant $tenant, PaymentMethodPayload $payload, bool $makeDefault = true): PaymentMethod
    {
        if ($makeDefault) {
            PaymentMethod::query()
                ->where('tenant_id', $tenant->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        return PaymentMethod::query()->create([
            'tenant_id' => $tenant->id,
            'gateway' => PaymentGateway::from($payload->gateway),
            'status' => PaymentMethodStatus::Active,
            'external_id' => $payload->externalId,
            'customer_external_id' => $payload->customerExternalId,
            'authorization_code' => $payload->authorizationCode,
            'brand' => $payload->brand,
            'last_four' => $payload->lastFour,
            'exp_month' => $payload->expMonth,
            'exp_year' => $payload->expYear,
            'is_default' => $makeDefault,
            'meta' => $payload->meta,
        ]);
    }

    public function refundVerificationIfNeeded(PaymentMethodPayload $payload): void
    {
        if (!$payload->shouldRefund || $payload->chargedAmount <= 0 || blank($payload->refundReference)) {
            return;
        }

        $payment = new Payment([
            'gateway_reference' => $payload->refundReference,
            'currency' => $payload->currency,
            'amount' => $payload->chargedAmount,
        ]);

        $this->gateways->driver($payload->gateway)->refund(
            $payment,
            $payload->chargedAmount,
            ['reason' => 'Signup card verification refund'],
        );
    }
}
