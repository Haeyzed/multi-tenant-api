<?php

declare(strict_types=1);

use App\Enums\Central\PaymentStatus;
use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\Tenant;
use App\Payments\Drivers\PaystackDriver;
use App\Payments\Drivers\StripeDriver;
use App\Services\Central\Billing\PaymentService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['payments.mode' => 'live']);
});

it('creates a live stripe checkout session when charging without a payment method', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'api.stripe.com/v1/checkout/sessions' => Http::response([
            'id' => 'cs_test_123',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
            'payment_intent' => 'pi_test_123',
        ], 200),
    ]);

    config(['payments.stripe.secret' => 'sk_test_123']);

    $tenant = Tenant::factory()->create(['email' => 'billing@example.test']);
    $invoice = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'total' => 50,
        'amount_paid' => 0,
        'currency' => 'USD',
    ]);

    $payment = app(PaymentService::class)->chargeInvoice($invoice, ['gateway' => 'stripe']);

    expect($payment->status)->toBe(PaymentStatus::PROCESSING)
        ->and($payment->gateway_reference)->toBe('cs_test_123')
        ->and($payment->attempts)->toHaveCount(1)
        ->and($payment->attempts->first()->payload['checkout_url'])->toBe('https://checkout.stripe.com/c/pay/cs_test_123');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/checkout/sessions'));
});

it('initializes a live paystack transaction for hosted checkout', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'message' => 'Authorization URL created',
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/abc',
                'access_code' => 'access_abc',
                'reference' => 'PSK_REF',
            ],
        ], 200),
    ]);

    config(['payments.paystack.secret' => 'sk_test_paystack']);

    $tenant = Tenant::factory()->create(['email' => 'paystack@example.test']);
    $invoice = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'total' => 1000,
        'amount_paid' => 0,
        'currency' => 'NGN',
    ]);
    $payment = Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'invoice_id' => $invoice->id,
        'amount' => 1000,
        'currency' => 'NGN',
        'status' => PaymentStatus::PROCESSING,
    ]);

    $result = app(PaystackDriver::class)->charge($invoice, $payment);

    expect($result->isPending())->toBeTrue()
        ->and($result->checkoutUrl())->toBe('https://checkout.paystack.com/abc');
});

it('fails stripe charge when credentials are missing in test mode', function (): void {
    config([
        'payments.mode' => 'test',
        'payments.stripe.secret' => null,
    ]);

    $tenant = Tenant::factory()->create();
    $invoice = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'total' => 10,
        'amount_paid' => 0,
    ]);
    $payment = Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'invoice_id' => $invoice->id,
        'amount' => 10,
    ]);

    $result = app(StripeDriver::class)->charge($invoice, $payment);

    expect($result->successful)->toBeFalse()
        ->and($result->message)->toContain('not configured');
});
