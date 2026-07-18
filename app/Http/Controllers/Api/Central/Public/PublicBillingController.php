<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Central\PaymentResource;
use App\Models\Central\Invoice;
use App\Models\Central\Subscription;
use App\Services\Central\Billing\BillingReturnService;
use App\Services\Central\Billing\PublicInvoicePaymentService;
use App\Services\Central\Billing\TrialBillingService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public signed billing conversion endpoints for trial checkout and invoice pay.
 */
#[Group('Central Public Billing', description: 'Signed trial checkout and invoice payment.', weight: 16)]
final class PublicBillingController extends Controller
{
    public function __construct(
        private readonly TrialBillingService $trialBillingService,
        private readonly BillingReturnService $billingReturns,
        private readonly PublicInvoicePaymentService $publicInvoices,
    ) {}

    /**
     * Start checkout for a trialing or past_due subscription via signed URL.
     *
     * Redirects to the gateway checkout_url by default. Pass format=json for JSON.
     */
    #[Endpoint(
        operationId: 'public.billing.checkout',
        title: 'Trial checkout',
        description: 'Signed URL used in trial emails. Creates/charges the conversion invoice and redirects to the gateway checkout URL (or returns JSON when format=json).',
    )]
    public function checkout(Request $request, Subscription $subscription): RedirectResponse|JsonResponse
    {
        $asJson = $request->query('format') === 'json'
            || $request->wantsJson();

        return $this->trialBillingService->checkoutResponse($subscription, $asJson);
    }

    /**
     * Show a publicly accessible invoice via signed URL.
     */
    #[Endpoint(
        operationId: 'public.billing.invoices.show',
        title: 'Public invoice',
        description: 'Signed URL used in invoice payment emails. Returns the invoice, currency-filtered gateways, and whether payment is allowed.',
    )]
    public function showInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        $this->publicInvoices->assertValidAccess($request, $invoice);

        return $this->success(
            $this->publicInvoices->showPayload($invoice),
            'Invoice retrieved successfully.',
        );
    }

    /**
     * Pay a publicly accessible invoice with a customer-selected gateway.
     *
     * Requires the same expires/signature query params as the show link.
     */
    #[Endpoint(
        operationId: 'public.billing.invoices.pay',
        title: 'Pay public invoice',
        description: 'Charge an invoice using a gateway that supports the invoice currency. Requires a valid signed show-link signature.',
    )]
    public function payInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        $this->publicInvoices->assertValidAccess($request, $invoice);

        $data = $request->validate([
            'gateway' => ['required', 'string', 'max:50'],
        ]);

        $result = $this->publicInvoices->pay($invoice, $data['gateway']);

        $message = $result['completed']
            ? 'Payment completed successfully.'
            : 'Checkout session created successfully.';

        return $this->success([
            'checkout_url' => $result['checkout_url'],
            'completed' => $result['completed'],
            'payment_id' => $result['payment']->id,
            'invoice_id' => $result['invoice']->id,
            'payment' => new PaymentResource($result['payment']->loadMissing(['attempts', 'invoice'])),
        ], $message);
    }

    /**
     * Finalize a hosted checkout after the customer returns from the gateway.
     *
     * Verifies the provider reference (Paystack) and activates the subscription
     * when payment succeeds, even if the webhook has not arrived yet.
     */
    #[Endpoint(
        operationId: 'public.billing.success',
        title: 'Checkout success return',
        description: 'Called by the frontend success page with payment/reference query params from the gateway redirect.',
    )]
    public function showSuccess(Request $request): JsonResponse
    {
        $result = $this->billingReturns->handleSuccess($request->query());

        return $this->success([
            'completed' => $result['completed'],
            'message' => $result['message'],
            'payment' => $result['payment'] !== null
                ? new PaymentResource($result['payment']->loadMissing(['invoice', 'subscription']))
                : null,
        ], $result['message']);
    }

    #[Endpoint(
        operationId: 'public.billing.cancel',
        title: 'Checkout cancel return',
        description: 'Called by the frontend cancel page after the customer abandons gateway checkout.',
    )]
    public function showCancel(Request $request): JsonResponse
    {
        $result = $this->billingReturns->handleCancel($request->query());

        return $this->success([
            'message' => $result['message'],
            'payment' => $result['payment'] !== null
                ? new PaymentResource($result['payment'])
                : null,
        ], $result['message']);
    }
}
