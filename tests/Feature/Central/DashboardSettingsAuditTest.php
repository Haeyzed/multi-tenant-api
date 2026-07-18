<?php

declare(strict_types=1);

use App\Enums\Central\PaymentStatus;
use App\Enums\Central\SettingGroup;
use App\Enums\Central\SettingType;
use App\Enums\Central\SubscriptionInterval;
use App\Enums\Central\SubscriptionStatus;
use App\Enums\Central\TenantStatus;
use App\Models\Central\Payment;
use App\Models\Central\Plan;
use App\Models\Central\Setting;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Services\Central\Billing\BillingSettings;
use App\Services\Central\Billing\PaymentGatewayResolver;
use App\Services\Central\Settings\SettingService;
use Database\Seeders\Central\SettingSeeder;

it('returns dashboard overview revenue charts and health', function (): void {
    actingAsCentralUser(['dashboard.view', 'dashboard.health']);

    $tenant = Tenant::factory()->create(['status' => TenantStatus::ACTIVE]);
    $plan = Plan::factory()->create(['price' => 120, 'billing_interval' => SubscriptionInterval::YEARLY]);

    Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::ACTIVE,
        'price' => 120,
        'billing_interval' => SubscriptionInterval::YEARLY,
        'currency' => 'USD',
    ]);

    Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => PaymentStatus::COMPLETED,
        'amount' => 50,
        'paid_at' => now(),
    ]);

    $this->getJson('/api/v1/dashboard')
        ->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.statistics.tenants.total', 1)
        ->assertJsonPath('data.revenue.mrr', 10);

    $this->getJson('/api/v1/dashboard/revenue')
        ->assertSuccessful()
        ->assertJsonPath('data.arr', 120);

    $this->getJson('/api/v1/dashboard/charts?days=7')
        ->assertSuccessful()
        ->assertJsonPath('status', true);

    $this->getJson('/api/v1/dashboard/growth?days=30')
        ->assertSuccessful()
        ->assertJsonPath('status', true);

    $this->getJson('/api/v1/dashboard/health')
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'healthy');

    $this->getJson('/api/v1/dashboard/notifications')
        ->assertSuccessful()
        ->assertJsonPath('data.unread', 0);
});

it('manages global settings across groups', function (): void {
    actingAsCentralUser([
        'settings.view',
        'settings.create',
        'settings.update',
        'settings.delete',
    ]);

    $this->seed(SettingSeeder::class);

    $this->getJson('/api/v1/settings/groups')
        ->assertSuccessful()
        ->assertJsonPath('status', true);

    $this->getJson('/api/v1/settings?group=platform')
        ->assertSuccessful()
        ->assertJsonPath('status', true);

    $created = $this->postJson('/api/v1/settings', [
        'group' => SettingGroup::Api->value,
        'key' => 'api.custom_header',
        'label' => 'Custom Header',
        'type' => SettingType::STRING->value,
        'value' => 'X-Tenant',
        'is_public' => false,
    ])->assertCreated()
        ->assertJsonPath('data.key', 'api.custom_header');

    $settingId = $created->json('data.id');

    $this->putJson("/api/v1/settings/{$settingId}", [
        'value' => 'X-Custom',
    ])->assertSuccessful()
        ->assertJsonPath('data.value', 'X-Custom');

    $this->putJson('/api/v1/settings/bulk', [
        'settings' => [
            'platform.name' => 'Acme Central',
        ],
    ])->assertSuccessful();

    expect(Setting::query()->where('key', 'platform.name')->first()?->resolvedValue())->toBe('Acme Central');

    $this->deleteJson("/api/v1/settings/{$settingId}")
        ->assertSuccessful();
});

it('exposes and validates tenant policy settings', function (): void {
    $this->seed(SettingSeeder::class);
    actingAsCentralUser(['settings.view', 'settings.update']);

    $this->getJson('/api/v1/settings/groups')
        ->assertSuccessful()
        ->assertJsonFragment(['value' => 'tenant', 'label' => 'Tenant']);

    $this->getJson('/api/v1/settings?group=tenant')
        ->assertSuccessful()
        ->assertJsonFragment(['key' => 'tenant.default_trial_days'])
        ->assertJsonFragment(['key' => 'tenant.allow_custom_domains']);

    $this->putJson('/api/v1/settings/bulk', [
        'settings' => ['tenant.default_trial_days' => 366],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['settings.tenant.default_trial_days']);

    $this->putJson('/api/v1/settings/bulk', [
        'settings' => ['tenant.auto_generate_domain' => 'not-a-boolean'],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['settings.tenant.auto_generate_domain']);

    $this->putJson('/api/v1/settings/bulk', [
        'settings' => [
            'tenant.default_trial_days' => 21,
            'tenant.owner_invite_ttl_hours' => 48,
            'tenant.auto_generate_domain' => false,
        ],
    ])->assertSuccessful();

    expect(Setting::query()->where('key', 'tenant.default_trial_days')->firstOrFail()->resolvedValue())
        ->toBe(21)
        ->and(Setting::query()->where('key', 'tenant.owner_invite_ttl_hours')->firstOrFail()->resolvedValue())
        ->toBe(48)
        ->and(Setting::query()->where('key', 'tenant.auto_generate_domain')->firstOrFail()->resolvedValue())
        ->toBeFalse();
});

it('exposes public invoice settings without authentication', function (): void {
    $this->seed(SettingSeeder::class);

    $this->getJson('/api/v1/public/settings?group=invoice')
        ->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonFragment(['invoice.company_name' => 'Multi Tenant API'])
        ->assertJsonFragment(['invoice.number_prefix' => 'INV-']);
});

it('applies mail and storage settings to runtime config', function (): void {
    $this->seed(SettingSeeder::class);

    actingAsCentralUser(['settings.view', 'settings.update']);

    $this->putJson('/api/v1/settings/bulk', [
        'settings' => [
            'mail.mailer' => 'smtp',
            'mail.host' => 'smtp.example.test',
            'mail.port' => 587,
            'mail.username' => 'mailer@example.test',
            'mail.from_address' => 'noreply@example.test',
            'mail.from_name' => 'Example Mail',
            'storage.default_disk' => 's3',
            'storage.s3_key' => 'AKIAEXAMPLE',
            'storage.s3_region' => 'eu-west-1',
            'storage.s3_bucket' => 'platform-files',
            'storage.s3_use_path_style_endpoint' => true,
            'billing.mode' => 'live',
            'billing.default_gateway' => 'paystack',
            'billing.paystack_public' => 'pk_test_example',
            'billing.paystack_secret' => 'sk_test_example',
        ],
    ])->assertSuccessful();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.example.test')
        ->and(config('mail.mailers.smtp.port'))->toBe(587)
        ->and(config('mail.mailers.smtp.username'))->toBe('mailer@example.test')
        ->and(config('mail.from.address'))->toBe('noreply@example.test')
        ->and(config('mail.from.name'))->toBe('Example Mail')
        ->and(config('filesystems.default'))->toBe('s3')
        ->and(config('filesystems.disks.s3.key'))->toBe('AKIAEXAMPLE')
        ->and(config('filesystems.disks.s3.region'))->toBe('eu-west-1')
        ->and(config('filesystems.disks.s3.bucket'))->toBe('platform-files')
        ->and(config('filesystems.disks.s3.use_path_style_endpoint'))->toBeTrue()
        ->and(config('payments.mode'))->toBe('live')
        ->and(config('payments.default'))->toBe('paystack')
        ->and(config('payments.paystack.public'))->toBe('pk_test_example')
        ->and(config('payments.paystack.secret'))->toBe('sk_test_example');

    $this->getJson('/api/v1/settings?group=mail')
        ->assertSuccessful()
        ->assertJsonFragment(['key' => 'mail.mailer'])
        ->assertJsonFragment(['key' => 'mail.host']);
});

it('validates and immediately applies multi-currency payment policy', function (): void {
    $this->seed(SettingSeeder::class);
    actingAsCentralUser(['settings.view', 'settings.update']);

    $providers = Setting::query()
        ->where('key', 'billing.provider_currencies')
        ->firstOrFail()
        ->resolvedValue();
    $routes = Setting::query()
        ->where('key', 'billing.gateway_by_currency')
        ->firstOrFail()
        ->resolvedValue();

    $this->putJson('/api/v1/settings/bulk', [
        'settings' => [
            'billing.provider_currencies' => [
                ...$providers,
                'paystack' => [...$providers['paystack'], 'KES'],
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['settings.billing.provider_currencies.paystack']);

    $this->putJson('/api/v1/settings/bulk', [
        'settings' => [
            'billing.provider_currencies' => $providers,
            'billing.gateway_by_currency' => [
                ...$routes,
                'KES' => 'paystack',
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['settings.billing.gateway_by_currency.KES']);

    $providers['paystack'] = array_values(array_diff($providers['paystack'], ['NGN']));
    $routes['NGN'] = 'flutterwave';

    $this->putJson('/api/v1/settings/bulk', [
        'settings' => [
            'billing.provider_currencies' => $providers,
            'billing.gateway_by_currency' => $routes,
        ],
    ])->assertSuccessful();

    config([
        'payments.paystack.secret' => 'sk_test',
        'payments.flutterwave.secret' => 'sk_test',
        'payments.stripe.secret' => 'sk_test',
    ]);
    app(SettingService::class)->forgetCache();

    $options = app(PaymentGatewayResolver::class)->optionsForCurrency('NGN');

    expect(collect($options)->pluck('value')->all())
        ->not->toContain('paystack')
        ->and(collect($options)->firstWhere('recommended', true)['value'])
        ->toBe('flutterwave');
});

it('validates and resolves billing lifecycle policy settings', function (): void {
    $this->seed(SettingSeeder::class);
    actingAsCentralUser(['settings.view', 'settings.update']);

    $this->getJson('/api/v1/settings?group=billing')
        ->assertSuccessful()
        ->assertJsonFragment(['key' => 'billing.invoice_due_days'])
        ->assertJsonFragment(['key' => 'billing.price_fallback_mode']);

    $this->putJson('/api/v1/settings/bulk', [
        'settings' => [
            'billing.invoice_due_days' => 366,
            'billing.default_interval' => 'lifetime',
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['settings.billing.invoice_due_days']);

    $invoiceDueSetting = Setting::query()
        ->where('key', 'billing.invoice_due_days')
        ->firstOrFail();

    $this->putJson("/api/v1/settings/{$invoiceDueSetting->id}", [
        'value' => -1,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['value']);

    $this->putJson('/api/v1/settings/bulk', [
        'settings' => [
            'billing.invoice_due_days' => 14,
            'billing.past_due_grace_days' => 5,
            'billing.trial_reminder_days' => 7,
            'billing.checkout_link_ttl_hours' => 48,
            'billing.invoice_link_ttl_hours' => 96,
            'billing.default_interval' => 'quarterly',
            'billing.price_fallback_mode' => 'same_interval',
            'invoice.number_prefix' => 'BILL-',
        ],
    ])->assertSuccessful();

    $settings = app(BillingSettings::class);

    expect($settings->invoiceDueDays())->toBe(14)
        ->and($settings->pastDueGraceDays())->toBe(5)
        ->and($settings->trialReminderDays())->toBe(7)
        ->and($settings->checkoutLinkTtlHours())->toBe(48)
        ->and($settings->invoiceLinkTtlHours())->toBe(96)
        ->and($settings->defaultInterval())->toBe(SubscriptionInterval::QUARTERLY)
        ->and($settings->priceFallbackMode())->toBe('same_interval')
        ->and($settings->invoiceNumberPrefix())->toBe('BILL-');
});

it('sends a test email using configured mail settings', function (): void {
    $this->seed(SettingSeeder::class);

    actingAsCentralUser(['settings.update']);

    Illuminate\Support\Facades\Mail::fake();

    $this->putJson('/api/v1/settings/bulk', [
        'settings' => [
            'mail.mailer' => 'array',
            'mail.from_address' => 'noreply@example.test',
            'mail.from_name' => 'Example Mail',
        ],
    ])->assertSuccessful();

    $this->postJson('/api/v1/settings/mail/test', [
        'email' => 'admin@example.test',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.email', 'admin@example.test');

    Illuminate\Support\Facades\Mail::assertSent(App\Mail\Central\SettingsTestMail::class);
});

it('searches filters and exports audit logs', function (): void {
    $user = actingAsCentralUser(['audit.view', 'audit.export', 'tenants.create', 'tenants.view']);

    $tenant = Tenant::factory()->create();

    activity()
        ->causedBy($user)
        ->performedOn($tenant)
        ->event('created')
        ->log('tenant.created');

    $this->getJson('/api/v1/audit-logs?search=tenant')
        ->assertSuccessful()
        ->assertJsonPath('status', true);

    $activityId = $this->getJson('/api/v1/audit-logs')->json('data.0.id');

    $this->getJson("/api/v1/audit-logs/{$activityId}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $activityId);

    $this->getJson("/api/v1/users/{$user->id}/audit-logs")
        ->assertSuccessful();

    $this->getJson("/api/v1/tenants/{$tenant->id}/audit-logs")
        ->assertSuccessful();

    $this->get('/api/v1/audit-logs/export')
        ->assertSuccessful()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});
