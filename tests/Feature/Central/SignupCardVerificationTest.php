<?php

declare(strict_types=1);

use App\Enums\Central\PaymentGateway;
use App\Enums\Central\PlanStatus;
use App\Enums\Central\PlanVisibility;
use App\Enums\Central\SettingGroup;
use App\Enums\Central\SettingType;
use App\Enums\Central\SignupIntentStatus;
use App\Enums\Central\SubscriptionStatus;
use App\Enums\Central\TenantStatus;
use App\Models\Central\BillingProfile;
use App\Models\Central\PaymentMethod;
use App\Models\Central\Plan;
use App\Models\Central\Setting;
use App\Models\Central\SignupIntent;
use App\Models\Central\SubscriptionHistory;
use App\Models\Central\Tenant;
use App\Services\Central\Billing\CardVerificationService;
use App\Services\Central\Settings\SettingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    cleanupTenantDatabases();
    app(SettingService::class)->forgetCache();
});

afterEach(function (): void {
    cleanupTenantDatabases();
});

it('starts signup setup without creating a tenant', function (): void {
    seedWorldCountry('NG', 'Nigeria', 'NGN');

    config([
        'payments.mode' => 'test',
        'payments.default' => 'paystack',
        'payments.paystack.secret' => 'sk_test_paystack',
        'billing.frontend_url' => 'https://app.example.test',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/setup',
                'access_code' => 'access_setup',
                'reference' => 'SIGNUP_REF',
            ],
        ], 200),
    ]);

    $plan = Plan::factory()->create([
        'trial_days' => 7,
        'currency' => 'NGN',
        'price' => 15000,
        'status' => PlanStatus::Active,
        'visibility' => PlanVisibility::Public,
    ]);

    $response = $this->postJson('/api/v1/public/signup/setup', [
        'name' => 'Verify Co',
        'email' => 'owner@verify.test',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
        'plan_id' => $plan->id,
        'country' => 'NG',
        'gateway' => 'paystack',
        'owner_name' => 'Owner',
        'domain' => 'verify.test',
    ])->assertCreated()
        ->assertJsonPath('data.gateway', 'paystack')
        ->assertJsonPath('data.checkout_url', 'https://checkout.paystack.com/setup');

    $intentId = $response->json('data.signup_intent_id');

    $intent = SignupIntent::query()->findOrFail($intentId);

    expect($intent->status)->toBe(SignupIntentStatus::Pending)
        ->and($intent->payload)->not->toHaveKeys(['password', 'password_confirmation'])
        ->and($intent->password_secret)->toBe('Password1!')
        ->and(DB::table('signup_intents')->where('id', $intentId)->value('password_secret'))
        ->not->toBe('Password1!')
        ->and(Tenant::query()->where('email', 'owner@verify.test')->exists())->toBeFalse();
});

it('completes signup after successful card verification', function (): void {
    seedWorldCountry('NG', 'Nigeria', 'NGN');

    config([
        'payments.mode' => 'test',
        'payments.default' => 'paystack',
        'payments.paystack.secret' => 'sk_test_paystack',
        'billing.frontend_url' => 'https://app.example.test',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/setup',
                'access_code' => 'access_setup',
                'reference' => 'SIGNUP_REF',
            ],
        ], 200),
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'amount' => 100,
                'currency' => 'NGN',
                'reference' => 'SIGNUP_REF',
                'authorization' => [
                    'authorization_code' => 'AUTH_test',
                    'brand' => 'visa',
                    'last4' => '4081',
                    'exp_month' => 12,
                    'exp_year' => 2030,
                ],
                'customer' => ['email' => 'owner2@verify.test'],
            ],
        ], 200),
        'api.paystack.co/refund' => Http::response([
            'status' => true,
            'data' => ['id' => 1],
        ], 200),
    ]);

    $plan = Plan::factory()->create([
        'trial_days' => 7,
        'currency' => 'NGN',
        'price' => 15000,
        'status' => PlanStatus::Active,
        'visibility' => PlanVisibility::Public,
    ]);

    $setup = $this->postJson('/api/v1/public/signup/setup', [
        'name' => 'Verify Co 2',
        'email' => 'owner2@verify.test',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
        'plan_id' => $plan->id,
        'country' => 'NG',
        'gateway' => 'paystack',
        'owner_name' => 'Owner',
        'domain' => 'verify2.test',
    ])->assertCreated();

    $intentId = $setup->json('data.signup_intent_id');
    $intent = SignupIntent::query()->findOrFail($intentId);
    $intent->update([
        'payload' => [
            ...$intent->payload,
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ],
        'password_secret' => null,
    ]);

    $this->postJson('/api/v1/public/signup/complete', [
        'signup_intent_id' => $intentId,
        'trxref' => $intent->gateway_reference,
    ])->assertCreated()
        ->assertJsonPath('data.tenant.status', TenantStatus::TRIAL->value)
        ->assertJsonPath('data.subscription.status', SubscriptionStatus::TRIALING->value);

    $tenant = Tenant::query()->where('email', 'owner2@verify.test')->first();

    expect($tenant)->not->toBeNull()
        ->and(PaymentMethod::query()->where('tenant_id', $tenant->id)->where('is_default', true)->exists())->toBeTrue()
        ->and(BillingProfile::query()->where('tenant_id', $tenant->id)->first()?->preferred_gateway)->toBe('paystack')
        ->and(BillingProfile::query()->where('tenant_id', $tenant->id)->first()?->currency)->toBe('NGN')
        ->and(SubscriptionHistory::query()->where('event', 'created')->whereHas('subscription', fn ($q) => $q->where('tenant_id', $tenant->id))->exists())->toBeTrue()
        ->and($intent->fresh()->status)->toBe(SignupIntentStatus::Completed)
        ->and($intent->fresh()->password_secret)->toBeNull()
        ->and($intent->fresh()->payload)->not->toHaveKeys(['password', 'password_confirmation']);

    $this->postJson('/api/v1/public/signup/complete', [
        'signup_intent_id' => $intentId,
        'trxref' => $intent->gateway_reference,
    ])->assertCreated();

    expect(Tenant::query()->where('email', 'owner2@verify.test')->count())->toBe(1);
});

it('rejects direct signup when card verification is required', function (): void {
    seedWorldCountry('NG', 'Nigeria', 'NGN');

    Setting::query()->updateOrCreate(
        ['key' => 'billing.signup_card_verification'],
        [
            'group' => SettingGroup::Billing,
            'label' => 'Require Card Verification at Signup',
            'type' => SettingType::BOOLEAN,
            'value' => '1',
            'default_value' => ['value' => true],
        ],
    );
    app(SettingService::class)->forgetCache();

    $plan = Plan::factory()->create([
        'trial_days' => 7,
        'status' => PlanStatus::Active,
        'visibility' => PlanVisibility::Public,
    ]);

    $this->postJson('/api/v1/public/signup', [
        'name' => 'Blocked Co',
        'email' => 'blocked@verify.test',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
        'plan_id' => $plan->id,
        'country' => 'NG',
    ])->assertUnprocessable();
});

it('returns currency and filtered gateways for signup payment options', function (): void {
    seedWorldCountry('NG', 'Nigeria', 'NGN');
    seedWorldCountry('US', 'United States', 'USD');

    config([
        'payments.mode' => 'test',
        'payments.paystack.secret' => 'sk_test_paystack',
        'payments.flutterwave.secret' => 'sk_test_flutterwave',
        'payments.stripe.secret' => 'sk_test_stripe',
    ]);

    $this->getJson('/api/v1/public/signup/payment-options?country=NG')
        ->assertOk()
        ->assertJsonPath('data.currency', 'NGN')
        ->assertJsonFragment(['value' => 'paystack', 'recommended' => true])
        ->assertJsonFragment(['value' => 'flutterwave'])
        ->assertJsonFragment(['value' => 'stripe']);

    $us = $this->getJson('/api/v1/public/signup/payment-options?country=US')
        ->assertOk()
        ->assertJsonPath('data.currency', 'USD');

    $values = collect($us->json('data.gateways'))->pluck('value')->all();

    expect($values)->toContain('stripe', 'paystack', 'flutterwave');
});

it('rejects signup setup when gateway is missing or unsupported', function (): void {
    seedWorldCountry('NG', 'Nigeria', 'NGN');

    config([
        'payments.mode' => 'test',
        'payments.paystack.secret' => 'sk_test_paystack',
        'payments.stripe.secret' => null,
        'payments.flutterwave.secret' => null,
    ]);

    $plan = Plan::factory()->create([
        'trial_days' => 7,
        'currency' => 'NGN',
        'price' => 15000,
        'status' => PlanStatus::Active,
        'visibility' => PlanVisibility::Public,
    ]);

    $payload = [
        'name' => 'Gateway Co',
        'email' => 'gateway@verify.test',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
        'plan_id' => $plan->id,
        'country' => 'NG',
        'owner_name' => 'Owner',
        'domain' => 'gateway.test',
    ];

    $this->postJson('/api/v1/public/signup/setup', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['gateway']);

    $this->postJson('/api/v1/public/signup/setup', [
        ...$payload,
        'gateway' => 'manual',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['gateway']);

    config(['payments.paystack.secret' => null, 'payments.stripe.secret' => 'sk_test']);

    $this->postJson('/api/v1/public/signup/setup', [
        ...$payload,
        'email' => 'gateway2@verify.test',
        'domain' => 'gateway2.test',
        'gateway' => 'paystack',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['gateway']);
});

it('starts signup setup with an explicitly selected supported gateway', function (): void {
    seedWorldCountry('NG', 'Nigeria', 'NGN');

    config([
        'payments.mode' => 'test',
        'payments.default' => 'paystack',
        'payments.paystack.secret' => 'sk_test_paystack',
        'payments.stripe.secret' => 'sk_test_stripe',
        'billing.frontend_url' => 'https://app.example.test',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.stripe.com/v1/customers' => Http::response([
            'id' => 'cus_test',
        ], 200),
        'api.stripe.com/v1/checkout/sessions' => Http::response([
            'id' => 'cs_test_setup',
            'url' => 'https://checkout.stripe.com/setup',
        ], 200),
    ]);

    $plan = Plan::factory()->create([
        'trial_days' => 7,
        'currency' => 'NGN',
        'price' => 15000,
        'status' => PlanStatus::Active,
        'visibility' => PlanVisibility::Public,
    ]);

    $this->postJson('/api/v1/public/signup/setup', [
        'name' => 'Stripe Choice Co',
        'email' => 'stripe-choice@verify.test',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
        'plan_id' => $plan->id,
        'country' => 'NG',
        'gateway' => 'stripe',
        'owner_name' => 'Owner',
        'domain' => 'stripe-choice.test',
    ])->assertCreated()
        ->assertJsonPath('data.gateway', 'stripe')
        ->assertJsonPath('data.currency', 'NGN')
        ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.com/setup');

    expect(SignupIntent::query()->where('email', 'stripe-choice@verify.test')->first()?->gateway)
        ->toBe(PaymentGateway::STRIPE)
        ->and((float) SignupIntent::query()->where('email', 'stripe-choice@verify.test')->value('verification_amount'))
        ->toBe(0.0);
});

it('resolves verification amounts by currency and provider minimum', function (): void {
    Setting::query()->updateOrCreate(
        ['key' => 'billing.card_verification_amounts'],
        [
            'group' => SettingGroup::Billing,
            'label' => 'Card Verification Amounts',
            'type' => SettingType::JSON,
            'value' => json_encode([
                'NGN' => 2,
                'USD' => 1,
                'KES' => 10,
            ], JSON_THROW_ON_ERROR),
            'default_value' => ['value' => []],
        ],
    );
    Setting::query()->updateOrCreate(
        ['key' => 'billing.card_verification_minimums'],
        [
            'group' => SettingGroup::Billing,
            'label' => 'Card Verification Minimums',
            'type' => SettingType::JSON,
            'value' => json_encode([
                'paystack' => ['NGN' => 5],
                'flutterwave' => ['KES' => 2],
                'stripe' => [],
            ], JSON_THROW_ON_ERROR),
            'default_value' => ['value' => []],
        ],
    );
    app(SettingService::class)->forgetCache();

    $verification = app(CardVerificationService::class);

    expect($verification->verificationAmount('NGN', 'paystack'))->toBe(5.0)
        ->and($verification->verificationAmount('USD', 'flutterwave'))->toBe(1.0)
        ->and($verification->verificationAmount('KES', 'flutterwave'))->toBe(10.0)
        ->and($verification->verificationAmount('USD', 'stripe'))->toBe(0.0);
});
