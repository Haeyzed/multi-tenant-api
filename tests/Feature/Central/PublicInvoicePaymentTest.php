<?php

declare(strict_types=1);

use App\Enums\Central\InvoiceStatus;
use App\Mail\Central\InvoicePaymentMail;
use App\Models\Central\Invoice;
use App\Models\Central\Tenant;
use App\Services\Central\Billing\PublicInvoicePaymentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

it('shows a signed public invoice with currency-filtered gateways', function (): void {
    config([
        'payments.mode' => 'test',
        'payments.stripe.secret' => 'sk_test',
        'payments.paystack.secret' => 'sk_test',
        'payments.flutterwave.secret' => 'sk_test',
    ]);

    $invoice = Invoice::factory()->create([
        'currency' => 'NGN',
        'status' => InvoiceStatus::OPEN,
        'total' => 50,
        'amount_paid' => 0,
    ]);

    URL::forceRootUrl((string) config('app.url'));

    $url = URL::temporarySignedRoute(
        'central.public.billing.invoices.show',
        now()->addHour(),
        ['invoice' => $invoice->id],
    );

    $this->getJson($url)
        ->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.invoice.id', $invoice->id)
        ->assertJsonPath('data.can_pay', true)
        ->assertJsonFragment(['value' => 'paystack'])
        ->assertJsonFragment(['value' => 'stripe']);
});

it('rejects unsigned public invoice links', function (): void {
    $invoice = Invoice::factory()->create();

    $this->getJson('/api/v1/public/billing/invoices/'.$invoice->id)
        ->assertForbidden();
});

it('pays a public invoice with a selected gateway in test mode', function (): void {
    config([
        'payments.mode' => 'test',
        'payments.paystack.secret' => 'sk_test_paystack',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/pay',
                'access_code' => 'access_pay',
                'reference' => 'PSK_PAY',
            ],
        ], 200),
    ]);

    $invoice = Invoice::factory()->create([
        'currency' => 'NGN',
        'status' => InvoiceStatus::OPEN,
        'total' => 40,
        'amount_paid' => 0,
    ]);

    URL::forceRootUrl((string) config('app.url'));

    $showUrl = URL::temporarySignedRoute(
        'central.public.billing.invoices.show',
        now()->addHour(),
        ['invoice' => $invoice->id],
    );

    $query = parse_url($showUrl, PHP_URL_QUERY) ?: '';

    $this->postJson('/api/v1/public/billing/invoices/'.$invoice->id.'/pay?'.$query, [
        'gateway' => 'paystack',
    ])
        ->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.completed', false)
        ->assertJsonPath('data.invoice_id', $invoice->id)
        ->assertJsonPath('data.checkout_url', 'https://checkout.paystack.com/pay');
});

it('rejects a gateway that does not support the invoice currency', function (): void {
    config([
        'payments.mode' => 'test',
        'payments.paystack.secret' => 'sk_test',
        'payments.stripe.secret' => 'sk_test',
        'payments.flutterwave.secret' => 'sk_test',
        'payments.provider_currencies.paystack' => ['NGN'],
    ]);

    $invoice = Invoice::factory()->create([
        'currency' => 'KES',
        'status' => InvoiceStatus::OPEN,
        'total' => 20,
        'amount_paid' => 0,
    ]);

    URL::forceRootUrl((string) config('app.url'));

    $showUrl = URL::temporarySignedRoute(
        'central.public.billing.invoices.show',
        now()->addHour(),
        ['invoice' => $invoice->id],
    );

    $query = parse_url($showUrl, PHP_URL_QUERY) ?: '';

    $this->postJson('/api/v1/public/billing/invoices/'.$invoice->id.'/pay?'.$query, [
        'gateway' => 'paystack',
    ])->assertUnprocessable();
});

it('sends a signed payment link email for an unpaid invoice', function (): void {
    Mail::fake();

    $tenant = Tenant::factory()->create([
        'email' => 'billing@tenant.test',
        'name' => 'Acme Tenant',
    ]);

    $invoice = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => InvoiceStatus::OPEN,
        'total' => 75,
        'amount_paid' => 0,
        'currency' => 'USD',
    ]);

    actingAsCentralUser(['billing.invoices.manage']);

    config(['billing.frontend_url' => 'https://app.example.test']);

    $this->postJson('/api/v1/invoices/'.$invoice->id.'/send-payment-link')
        ->assertSuccessful()
        ->assertJsonPath('data.email', 'billing@tenant.test')
        ->assertJsonPath('status', true);

    Mail::assertSent(InvoicePaymentMail::class, function (InvoicePaymentMail $mail) use ($invoice): bool {
        return $mail->invoiceNumber === $invoice->number
            && str_contains($mail->paymentUrl, '/central/billing/invoices/'.$invoice->id)
            && str_contains($mail->paymentUrl, 'signature=');
    });
});

it('builds frontend payment urls from the signed api show route', function (): void {
    config(['billing.frontend_url' => 'https://app.example.test']);

    $invoice = Invoice::factory()->create();

    $url = app(PublicInvoicePaymentService::class)->signedFrontendUrl($invoice);

    expect($url)->toStartWith('https://app.example.test/central/billing/invoices/'.$invoice->id)
        ->and($url)->toContain('signature=')
        ->and($url)->toContain('expires=');
});
