<?php

declare(strict_types=1);

use App\Enums\Central\PaymentStatus;
use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\Tenant;
use Illuminate\Support\Facades\Http;

it('completes a paystack payment from a signed webhook', function (): void {
    config([
        'payments.mode' => 'live',
        'payments.paystack.secret' => 'sk_test_paystack',
        'payments.paystack.webhook_secret' => 'sk_test_paystack',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => ['status' => 'success', 'reference' => 'PSK_WEBHOOK_1'],
        ], 200),
    ]);

    $tenant = Tenant::factory()->create(['email' => 'owner@example.test']);
    $invoice = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'total' => 25,
        'amount_paid' => 0,
    ]);
    $payment = Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'invoice_id' => $invoice->id,
        'amount' => 25,
        'status' => PaymentStatus::PROCESSING,
        'gateway' => 'paystack',
        'gateway_reference' => 'PSK_WEBHOOK_1',
    ]);

    $payload = json_encode([
        'event' => 'charge.success',
        'data' => [
            'reference' => 'PSK_WEBHOOK_1',
            'metadata' => ['payment_id' => $payment->id],
            'gateway_response' => 'Successful',
        ],
    ], JSON_THROW_ON_ERROR);

    $signature = hash_hmac('sha512', $payload, 'sk_test_paystack');

    $this->call(
        'POST',
        '/api/v1/webhooks/payments/paystack',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
        ],
        $payload,
    )->assertSuccessful()
        ->assertJsonPath('data.handled', true)
        ->assertJsonPath('data.status', 'completed');

    expect($payment->fresh()->status)->toBe(PaymentStatus::COMPLETED)
        ->and((float) $invoice->fresh()->amount_paid)->toBe(25.0);
});
