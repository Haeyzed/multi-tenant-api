<?php

declare(strict_types=1);

use App\Enums\Central\InvoiceStatus;
use App\Enums\Central\PlanStatus;
use App\Enums\Central\PlanVisibility;
use App\Enums\Central\SubscriptionInterval;
use App\Enums\Central\SubscriptionStatus;
use App\Enums\Central\TenantStatus;
use App\Mail\Tenant\TrialEndedMail;
use App\Mail\Tenant\TrialEndingMail;
use App\Models\Central\Invoice;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Services\Central\Billing\TrialBillingService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

it('lists public plan options as value and label pairs', function (): void {
    $public = Plan::factory()->create([
        'name' => 'Pro',
        'price' => 29,
        'billing_interval' => SubscriptionInterval::MONTHLY,
        'status' => PlanStatus::Active,
        'visibility' => PlanVisibility::Public,
        'sort_order' => 1,
    ]);

    Plan::factory()->create([
        'name' => 'Hidden Private',
        'status' => PlanStatus::Active,
        'visibility' => PlanVisibility::Private,
    ]);

    $this->getJson('/api/v1/public/plans/options')
        ->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.0.value', $public->id)
        ->assertJsonPath('data.0.label', 'Pro — NGN 29.00/mo')
        ->assertJsonCount(1, 'data');
});

it('sends a trial ending reminder once for subscriptions ending soon', function (): void {
    Mail::fake();

    $tenant = Tenant::factory()->create([
        'email' => 'owner@trial-ending.test',
        'status' => TenantStatus::TRIAL,
    ]);
    $plan = Plan::factory()->create(['name' => 'Growth']);

    $subscription = Subscription::factory()->trialing()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'trial_ends_at' => now()->addDays(2),
        'metadata' => [],
    ]);

    $this->artisan('billing:process-trials')->assertSuccessful();

    Mail::assertSent(TrialEndingMail::class, function (TrialEndingMail $mail) use ($tenant): bool {
        return $mail->hasTo($tenant->email)
            && str_contains($mail->checkoutUrl, '/central/billing/checkout/')
            && str_contains($mail->checkoutUrl, 'signature=');
    });

    expect($subscription->fresh()->metadata['trial_reminder_sent_at'] ?? null)->not->toBeNull();

    Mail::fake();
    $this->artisan('billing:process-trials')->assertSuccessful();
    Mail::assertNothingSent();
});

it('creates an invoice and marks the subscription past due when the trial ends', function (): void {
    Mail::fake();

    $tenant = Tenant::factory()->create([
        'email' => 'owner@trial-ended.test',
        'status' => TenantStatus::TRIAL,
    ]);
    $plan = Plan::factory()->create(['name' => 'Pro', 'price' => 49]);

    $subscription = Subscription::factory()->trialing()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'price' => 49,
        'trial_ends_at' => now()->subMinute(),
        'metadata' => [],
    ]);

    $this->artisan('billing:process-trials')->assertSuccessful();

    $subscription->refresh();
    $tenant->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::PAST_DUE)
        ->and($subscription->grace_ends_at)->not->toBeNull()
        ->and($subscription->metadata['trial_ended_processed_at'] ?? null)->not->toBeNull()
        ->and($tenant->status)->toBe(TenantStatus::GRACE_PERIOD)
        ->and(Invoice::query()->where('subscription_id', $subscription->id)->where('status', InvoiceStatus::OPEN)->exists())->toBeTrue();

    Mail::assertSent(TrialEndedMail::class);

    $invoiceCount = Invoice::query()->where('subscription_id', $subscription->id)->count();
    $this->artisan('billing:process-trials')->assertSuccessful();
    expect(Invoice::query()->where('subscription_id', $subscription->id)->count())->toBe($invoiceCount);
});

it('starts signed checkout and activates the subscription when payment completes', function (): void {
    $tenant = Tenant::factory()->create([
        'email' => 'owner@checkout.test',
        'status' => TenantStatus::GRACE_PERIOD,
    ]);
    $plan = Plan::factory()->create(['price' => 29, 'trial_days' => 0]);

    $subscription = Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'price' => 29,
        'status' => SubscriptionStatus::PAST_DUE,
        'grace_ends_at' => now()->addDays(3),
        'gateway' => 'manual',
    ]);

    URL::forceRootUrl((string) config('app.url'));

    $url = URL::temporarySignedRoute(
        'central.public.billing.checkout',
        now()->addHour(),
        ['subscription' => $subscription->id],
    );

    $this->getJson($url, ['Accept' => 'application/json'])
        ->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.completed', true)
        ->assertJsonPath('data.subscription_id', $subscription->id);

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::ACTIVE)
        ->and($tenant->fresh()->status)->toBe(TenantStatus::ACTIVE)
        ->and(Invoice::query()->where('subscription_id', $subscription->id)->where('status', InvoiceStatus::PAID)->exists())->toBeTrue();
});

it('rejects unsigned checkout links', function (): void {
    $subscription = Subscription::factory()->trialing()->create();

    $this->getJson('/api/v1/public/billing/checkout/'.$subscription->id.'?format=json')
        ->assertForbidden();
});

it('exposes signed checkout url builder for mail embeds', function (): void {
    config(['billing.frontend_url' => 'https://app.example.test']);

    $subscription = Subscription::factory()->trialing()->create();

    $url = app(TrialBillingService::class)->signedCheckoutUrl($subscription);
    $apiUrl = app(TrialBillingService::class)->signedApiCheckoutUrl($subscription);

    expect($url)->toStartWith('https://app.example.test/central/billing/checkout/'.$subscription->id)
        ->and($url)->toContain('signature=')
        ->and($url)->toContain('expires=')
        ->and($apiUrl)->toContain('/public/billing/checkout/'.$subscription->id)
        ->and($apiUrl)->toContain('signature=');
});
