<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Enums\Central\InvoiceStatus;
use App\Enums\Central\PaymentStatus;
use App\Http\Resources\Central\InvoiceResource;
use App\Mail\Central\InvoicePaymentMail;
use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Public signed invoice viewing and customer-initiated payment.
 */
final class PublicInvoicePaymentService
{
    public function __construct(
        private readonly PaymentService         $paymentService,
        private readonly PaymentGatewayResolver $gatewayResolver,
        private readonly BillingSettings        $billingSettings,
    )
    {
    }

    /**
     * Validate expires/signature against the signed show route (works for GET and POST).
     */
    public function assertValidAccess(Request $request, Invoice $invoice): void
    {
        URL::forceRootUrl(rtrim((string)config('app.url'), '/'));

        $expires = $request->query('expires');
        $signature = $request->query('signature');

        if (!filled($expires) || !filled($signature)) {
            abort(403, 'Invalid or expired invoice link.');
        }

        $uri = URL::route('central.public.billing.invoices.show', ['invoice' => $invoice->id], absolute: true);
        $uri .= (str_contains($uri, '?') ? '&' : '?') . http_build_query([
                'expires' => $expires,
                'signature' => $signature,
            ]);

        if (!URL::hasValidSignature(Request::create($uri, 'GET'))) {
            abort(403, 'Invalid or expired invoice link.');
        }
    }

    /**
     * @return array{invoice: InvoiceResource, gateways: list<array{value: string, label: string, recommended: bool}>, can_pay: bool}
     */
    public function showPayload(Invoice $invoice): array
    {
        $invoice->loadMissing(['tenant', 'items', 'billingAddress', 'subscription.plan']);

        $gateways = $this->gatewayResolver->optionsForCurrency($invoice->currency);

        return [
            'invoice' => new InvoiceResource($invoice),
            'gateways' => $gateways,
            'can_pay' => $this->canPay($invoice) && $gateways !== [],
        ];
    }

    public function canPay(Invoice $invoice): bool
    {
        if (!in_array($invoice->status, [
            InvoiceStatus::OPEN,
            InvoiceStatus::PENDING,
            InvoiceStatus::OVERDUE,
        ], true)) {
            return false;
        }

        return $invoice->balanceDue() > 0;
    }

    /**
     * Charge an invoice with a customer-selected gateway.
     *
     * @return array{payment: Payment, invoice: Invoice, checkout_url: string|null, completed: bool}
     *
     * @throws ValidationException|Throwable
     */
    public function pay(Invoice $invoice, string $gateway): array
    {
        if (!$this->canPay($invoice)) {
            throw ValidationException::withMessages([
                'invoice' => ['This invoice cannot be paid.'],
            ]);
        }

        $gateway = strtolower(trim($gateway));
        $available = collect($this->gatewayResolver->optionsForCurrency($invoice->currency))
            ->pluck('value')
            ->all();

        if ($gateway === '') {
            $recommended = collect($this->gatewayResolver->optionsForCurrency($invoice->currency))
                ->firstWhere('recommended', true);

            $gateway = is_array($recommended) ? (string)$recommended['value'] : '';
        }

        if ($gateway === '' || !in_array($gateway, $available, true)) {
            throw ValidationException::withMessages([
                'gateway' => ['Select a payment provider that supports this invoice currency.'],
            ]);
        }

        $payment = $this->paymentService->chargeInvoice($invoice, [
            'gateway' => $gateway,
        ]);

        $payment->loadMissing('attempts');

        $checkoutUrl = $payment->attempts->last()?->payload['checkout_url']
            ?? $payment->attempts->last()?->payload['authorization_url']
            ?? null;

        return [
            'payment' => $payment,
            'invoice' => $invoice->fresh() ?? $invoice,
            'checkout_url' => is_string($checkoutUrl) ? $checkoutUrl : null,
            'completed' => $payment->status === PaymentStatus::COMPLETED,
        ];
    }

    /**
     * Email a signed payment link to the tenant (or an override address).
     *
     * @return array{email: string, payment_url: string}
     *
     * @throws ValidationException
     */
    public function sendPaymentLink(Invoice $invoice, ?string $email = null): array
    {
        if (!$this->canPay($invoice)) {
            throw ValidationException::withMessages([
                'invoice' => ['This invoice cannot receive a payment link.'],
            ]);
        }

        $invoice->loadMissing('tenant');

        $recipient = filled($email) ? (string)$email : (string)($invoice->tenant?->email ?? '');

        if ($recipient === '') {
            throw ValidationException::withMessages([
                'email' => ['No recipient email is available for this invoice.'],
            ]);
        }

        $paymentUrl = $this->signedFrontendUrl($invoice);

        Mail::to($recipient)->send(new InvoicePaymentMail(
            invoiceNumber: (string)$invoice->number,
            tenantName: (string)($invoice->tenant?->name ?? 'Customer'),
            amount: (string)$invoice->total,
            currency: (string)$invoice->currency,
            paymentUrl: $paymentUrl,
        ));

        return [
            'email' => $recipient,
            'payment_url' => $paymentUrl,
        ];
    }

    /**
     * Frontend URL with expires/signature for email embeds.
     */
    public function signedFrontendUrl(Invoice $invoice): string
    {
        $apiUrl = $this->signedApiShowUrl($invoice);
        $query = parse_url($apiUrl, PHP_URL_QUERY) ?: '';

        $frontendBase = rtrim((string)config('billing.frontend_url'), '/');
        $path = str_replace(
            '{invoice}',
            (string)$invoice->id,
            (string)config('billing.frontend_invoice_path', '/central/billing/invoices/{invoice}'),
        );

        return $frontendBase . $path . ($query !== '' ? '?' . $query : '');
    }

    /**
     * Signed API show URL (source of truth for signature validation).
     */
    public function signedApiShowUrl(Invoice $invoice): string
    {
        $hours = $this->billingSettings->invoiceLinkTtlHours();

        URL::forceRootUrl(rtrim((string)config('app.url'), '/'));

        return URL::temporarySignedRoute(
            'central.public.billing.invoices.show',
            now()->addHours($hours),
            ['invoice' => $invoice->id],
        );
    }
}
