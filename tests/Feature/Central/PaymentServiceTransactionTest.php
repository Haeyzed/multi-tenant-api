<?php

declare(strict_types=1);

use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\Tenant;
use App\Services\Central\Billing\PaymentService;

it('charges an invoice without holding a gateway call inside the create transaction', function (): void {
    $tenant = Tenant::factory()->create();
    $invoice = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'total' => 25,
        'amount_paid' => 0,
    ]);

    $payment = app(PaymentService::class)->chargeInvoice($invoice, ['gateway' => 'manual']);

    expect($payment)->toBeInstanceOf(Payment::class)
        ->and($payment->attempts)->toHaveCount(1)
        ->and($payment->status->value)->toBe('completed')
        ->and(Payment::query()->whereKey($payment->id)->exists())->toBeTrue();
});
