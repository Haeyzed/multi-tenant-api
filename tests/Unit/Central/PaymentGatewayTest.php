<?php

declare(strict_types=1);

use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\Tenant;
use App\Payments\PaymentGatewayManager;
use App\Services\Central\Billing\PaymentService;

it('resolves configured payment gateway drivers', function (): void {
    $manager = app(PaymentGatewayManager::class);

    expect($manager->available())->toContain('stripe', 'paystack', 'paypal', 'paddle')
        ->and($manager->driver('stripe')->name())->toBe('stripe')
        ->and($manager->driver('paddle')->supportsRefunds())->toBeFalse();
});

it('records payment attempts when charging invoices', function (): void {
    $tenant = Tenant::factory()->create();
    $invoice = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'total' => 25,
        'amount_paid' => 0,
    ]);

    $payment = app(PaymentService::class)->chargeInvoice($invoice, ['gateway' => 'manual']);

    expect($payment)->toBeInstanceOf(Payment::class)
        ->and($payment->attempts)->toHaveCount(1)
        ->and($payment->status->value)->toBe('completed');
});
