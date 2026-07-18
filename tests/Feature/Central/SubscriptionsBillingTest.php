<?php

declare(strict_types=1);

use App\Enums\Central\InvoiceStatus;
use App\Enums\Central\PaymentStatus;
use App\Enums\Central\PlanStatus;
use App\Enums\Central\SubscriptionStatus;
use App\Models\Central\BillingAddress;
use App\Models\Central\Invoice;
use App\Models\Central\Plan;
use App\Models\Central\PlanPrice;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;

it('creates renews upgrades pauses and cancels subscriptions', function (): void {
    actingAsCentralUser([
        'subscriptions.view',
        'subscriptions.create',
        'subscriptions.manage',
        'billing.invoices.view',
        'billing.payments.charge',
        'billing.payments.view',
        'billing.payments.refund',
        'billing.gateways.view',
    ]);

    $tenant = Tenant::factory()->create();
    $starter = Plan::factory()->create(['name' => 'Starter', 'price' => 29]);
    $growth = Plan::factory()->create(['name' => 'Growth', 'price' => 79]);

    $created = $this->postJson('/api/v1/subscriptions', [
        'tenant_id' => $tenant->id,
        'plan_id' => $starter->id,
        'gateway' => 'stripe',
        'trial_days' => 0,
    ])->assertCreated()
        ->assertJsonPath('data.status', SubscriptionStatus::ACTIVE->value);

    $subscriptionId = $created->json('data.id');

    $this->getJson('/api/v1/subscriptions/options?tenant_id='.$tenant->id)
        ->assertSuccessful()
        ->assertJsonFragment([
            'value' => (string) $subscriptionId,
            'label' => "#{$subscriptionId} — Starter",
        ]);

    $this->postJson("/api/v1/subscriptions/{$subscriptionId}/upgrade", [
        'plan_id' => $growth->id,
    ])->assertSuccessful()
        ->assertJsonPath('data.plan_id', $growth->id);

    $this->postJson("/api/v1/subscriptions/{$subscriptionId}/pause")
        ->assertSuccessful()
        ->assertJsonPath('data.status', SubscriptionStatus::PAUSED->value);

    $this->postJson("/api/v1/subscriptions/{$subscriptionId}/resume")
        ->assertSuccessful()
        ->assertJsonPath('data.status', SubscriptionStatus::ACTIVE->value);

    $this->postJson("/api/v1/subscriptions/{$subscriptionId}/renew")
        ->assertSuccessful();

    $this->getJson("/api/v1/subscriptions/{$subscriptionId}/history")
        ->assertSuccessful()
        ->assertJsonPath('status', true);

    $this->postJson("/api/v1/subscriptions/{$subscriptionId}/cancel", [
        'immediately' => true,
        'reason' => 'Customer requested',
    ])->assertSuccessful()
        ->assertJsonPath('data.status', SubscriptionStatus::CANCELLED->value);
});

it('lists invoices and payments with tenant summaries', function (): void {
    actingAsCentralUser([
        'subscriptions.create',
        'subscriptions.view',
        'billing.invoices.view',
        'billing.invoices.manage',
        'billing.payments.view',
        'billing.payments.charge',
    ]);

    $tenant = Tenant::factory()->create(['name' => 'Acme Billing Co']);
    $plan = Plan::factory()->create([
        'price' => 50,
        'currency' => 'USD',
        'trial_days' => 0,
    ]);

    $this->postJson('/api/v1/subscriptions', [
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'gateway' => 'manual',
        'trial_days' => 0,
    ])->assertCreated();

    $invoice = $this->postJson('/api/v1/invoices', [
        'tenant_id' => $tenant->id,
        'items' => [
            ['description' => 'Setup fee', 'quantity' => 1, 'unit_price' => 25],
        ],
    ])->assertCreated();

    $invoiceId = $invoice->json('data.id');

    $this->getJson('/api/v1/invoices?search=Acme')
        ->assertSuccessful()
        ->assertJsonPath('data.0.tenant.name', 'Acme Billing Co');

    $this->getJson('/api/v1/invoices/statistics')
        ->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonStructure(['data' => ['total', 'open', 'paid', 'by_status', 'volume']]);

    $this->postJson("/api/v1/invoices/{$invoiceId}/charge", [
        'gateway' => 'manual',
    ])->assertCreated();

    $this->getJson('/api/v1/payments?gateway=manual&search=Acme')
        ->assertSuccessful()
        ->assertJsonPath('data.0.tenant.name', 'Acme Billing Co');

    $this->getJson('/api/v1/payments/statistics')
        ->assertSuccessful()
        ->assertJsonStructure(['data' => ['total', 'completed', 'by_status', 'by_gateway', 'volume']]);

    $this->getJson('/api/v1/subscriptions?gateway=manual&search=Acme')
        ->assertSuccessful()
        ->assertJsonPath('data.0.tenant.name', 'Acme Billing Co');

    $this->getJson('/api/v1/subscriptions/statistics')
        ->assertSuccessful()
        ->assertJsonStructure(['data' => ['total', 'active', 'by_status', 'by_gateway']]);
});

it('charges invoices through gateway drivers and refunds payments', function (): void {
    actingAsCentralUser([
        'subscriptions.create',
        'subscriptions.view',
        'billing.invoices.view',
        'billing.invoices.manage',
        'billing.payments.charge',
        'billing.payments.view',
        'billing.payments.refund',
        'billing.gateways.view',
        'billing.addresses.manage',
    ]);

    $tenant = Tenant::factory()->create();
    $plan = Plan::factory()->create([
        'price' => 50,
        'currency' => 'USD',
        'trial_days' => 0,
    ]);

    $subscription = $this->postJson('/api/v1/subscriptions', [
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'gateway' => 'paystack',
        'trial_days' => 0,
    ])->assertCreated();

    $invoiceId = collect($subscription->json('data.invoices'))->first()['id']
        ?? $this->getJson('/api/v1/invoices?tenant_id='.$tenant->id)->json('data.0.id');

    expect($invoiceId)->not->toBeNull();

    $charged = $this->postJson("/api/v1/invoices/{$invoiceId}/charge", [
        'gateway' => 'manual',
    ])->assertCreated()
        ->assertJsonPath('data.status', PaymentStatus::COMPLETED->value);

    $this->getJson("/api/v1/invoices/{$invoiceId}")
        ->assertSuccessful()
        ->assertJsonPath('data.status', InvoiceStatus::PAID->value);

    $paymentId = $charged->json('data.id');

    $this->postJson("/api/v1/payments/{$paymentId}/refund", [
        'amount' => 10,
        'reason' => 'partial credit',
    ])->assertCreated()
        ->assertJsonPath('data.status', PaymentStatus::REFUNDED->value);

    $this->getJson('/api/v1/payment-gateways')
        ->assertSuccessful()
        ->assertJsonPath('status', true);

    $options = $this->getJson('/api/v1/payment-gateways/options')
        ->assertSuccessful()
        ->json('data');

    expect($options)->toBeArray()->not->toBeEmpty()
        ->and($options[0])->toHaveKeys(['value', 'label'])
        ->and(collect($options)->firstWhere('value', 'stripe')['label'] ?? null)->toBe('Stripe');

    $this->postJson("/api/v1/tenants/{$tenant->id}/billing-addresses", [
        'name' => 'Acme Billing',
        'line1' => '1 Market St',
        'city' => 'San Francisco',
        'state' => 'CA',
        'postal_code' => '94105',
        'country' => 'US',
        'tax_id' => 'US123',
        'is_default' => true,
    ])->assertCreated();
});

it('marks subscriptions past due with grace period', function (): void {
    actingAsCentralUser(['subscriptions.create', 'subscriptions.manage', 'subscriptions.view']);

    $tenant = Tenant::factory()->create();
    $plan = Plan::factory()->create(['trial_days' => 0]);

    $id = $this->postJson('/api/v1/subscriptions', [
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'trial_days' => 0,
    ])->json('data.id');

    $this->postJson("/api/v1/subscriptions/{$id}/past-due", [
        'grace_days' => 5,
    ])->assertSuccessful()
        ->assertJsonPath('data.status', SubscriptionStatus::PAST_DUE->value)
        ->assertJsonPath('data.is_in_grace_period', true);
});

it('rejects inactive catalog records and cross-tenant billing ownership', function (): void {
    actingAsCentralUser([
        'subscriptions.create',
        'billing.invoices.manage',
    ]);

    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $inactivePlan = Plan::factory()->create(['status' => PlanStatus::Archived]);
    $activePlan = Plan::factory()->create(['status' => PlanStatus::Active]);
    $inactivePrice = PlanPrice::factory()->create([
        'plan_id' => $activePlan->id,
        'status' => PlanStatus::Inactive,
    ]);
    $otherAddress = BillingAddress::factory()->create([
        'tenant_id' => $otherTenant->id,
    ]);
    $otherSubscription = Subscription::factory()->create([
        'tenant_id' => $otherTenant->id,
    ]);

    $this->postJson('/api/v1/subscriptions', [
        'tenant_id' => $tenant->id,
        'plan_id' => $inactivePlan->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['plan_id']);

    $this->postJson('/api/v1/subscriptions', [
        'tenant_id' => $tenant->id,
        'plan_id' => $activePlan->id,
        'plan_price_id' => $inactivePrice->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['plan_price_id']);

    $this->postJson('/api/v1/subscriptions', [
        'tenant_id' => $tenant->id,
        'plan_id' => $activePlan->id,
        'billing_address_id' => $otherAddress->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_address_id']);

    $this->postJson('/api/v1/invoices', [
        'tenant_id' => $tenant->id,
        'subscription_id' => $otherSubscription->id,
        'billing_address_id' => $otherAddress->id,
        'items' => [
            ['description' => 'Invalid ownership', 'unit_price' => 10],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['subscription_id', 'billing_address_id']);
});

it('reuses lifecycle results for repeated HTTP idempotency keys', function (): void {
    actingAsCentralUser([
        'subscriptions.create',
        'subscriptions.manage',
    ]);

    $tenant = Tenant::factory()->create();
    $plan = Plan::factory()->create([
        'status' => PlanStatus::Active,
        'trial_days' => 0,
    ]);
    $payload = [
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'trial_days' => 0,
    ];

    $first = $this->withHeader('Idempotency-Key', 'create-subscription-1')
        ->postJson('/api/v1/subscriptions', $payload)
        ->assertCreated();
    $subscriptionId = $first->json('data.id');

    $this->withHeader('Idempotency-Key', 'create-subscription-1')
        ->postJson('/api/v1/subscriptions', $payload)
        ->assertCreated()
        ->assertJsonPath('data.id', $subscriptionId);

    expect(Subscription::query()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(Invoice::query()->where('subscription_id', $subscriptionId)->count())->toBe(1);

    $this->withHeader('Idempotency-Key', 'renew-subscription-1')
        ->postJson("/api/v1/subscriptions/{$subscriptionId}/renew")
        ->assertSuccessful();

    $periodEnd = Subscription::query()->findOrFail($subscriptionId)->current_period_end;
    $invoiceCount = Invoice::query()->where('subscription_id', $subscriptionId)->count();

    $this->withHeader('Idempotency-Key', 'renew-subscription-1')
        ->postJson("/api/v1/subscriptions/{$subscriptionId}/renew")
        ->assertSuccessful();

    expect(Subscription::query()->findOrFail($subscriptionId)->current_period_end?->equalTo($periodEnd))->toBeTrue()
        ->and(Invoice::query()->where('subscription_id', $subscriptionId)->count())->toBe($invoiceCount);
});
