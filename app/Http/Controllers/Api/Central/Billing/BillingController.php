<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Billing;

use App\Enums\Central\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Billing\ChargeInvoiceRequest;
use App\Http\Requests\Central\Billing\RefundPaymentRequest;
use App\Http\Requests\Central\Billing\SendInvoicePaymentLinkRequest;
use App\Http\Requests\Central\Billing\StoreBillingAddressRequest;
use App\Http\Requests\Central\Billing\StoreInvoiceRequest;
use App\Http\Requests\Central\Billing\UpdateBillingProfileRequest;
use App\Http\Resources\Central\BillingAddressResource;
use App\Http\Resources\Central\BillingProfileResource;
use App\Http\Resources\Central\InvoiceResource;
use App\Http\Resources\Central\PaymentResource;
use App\Http\Resources\Central\RefundResource;
use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\Tenant;
use App\Services\Central\Billing\BillingProfileService;
use App\Services\Central\Billing\InvoiceService;
use App\Services\Central\Billing\PaymentGatewayCatalogService;
use App\Services\Central\Billing\PaymentService;
use App\Services\Central\Billing\PublicInvoicePaymentService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Central admin endpoints for invoices, payments, refunds, addresses, and gateways.
 */
#[Group('Central Billing', description: 'Invoices, payments, refunds, addresses, gateways.', weight: 140)]
final class BillingController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly PaymentService $paymentService,
        private readonly PublicInvoicePaymentService $publicInvoices,
        private readonly BillingProfileService $billingProfiles,
        private readonly PaymentGatewayCatalogService $gatewayCatalog,
    ) {}

    #[Endpoint(operationId: 'billing.invoices', title: 'List invoices', description: 'Paginate invoices with optional filters.')]
    public function invoices(Request $request): JsonResponse
    {
        $this->authorize('viewBillingInvoices');

        $invoices = $this->invoiceService->paginate($request->only([
            'tenant_id', 'status', 'subscription_id', 'search', 'per_page',
        ]));

        return $this->paginated(InvoiceResource::collection($invoices), 'Invoices retrieved successfully.');
    }

    #[Endpoint(operationId: 'billing.invoiceStatistics', title: 'Invoice statistics', description: 'Return invoice overview statistics.')]
    public function invoiceStatistics(): JsonResponse
    {
        $this->authorize('viewBillingInvoices');

        return $this->success(
            $this->invoiceService->overviewStatistics(),
            'Invoice statistics retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'billing.storeInvoice', title: 'Create invoice', description: 'Create an invoice for a tenant/subscription.')]
    public function storeInvoice(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->create($request->validated());

        return $this->success(new InvoiceResource($invoice), 'Invoice created successfully.', 201);
    }

    #[Endpoint(operationId: 'billing.showInvoice', title: 'Show invoice', description: 'Return invoice details including line items.')]
    public function showInvoice(Invoice $invoice): JsonResponse
    {
        $this->authorize('viewBillingInvoices');
        $invoice->load(['tenant', 'items', 'billingAddress', 'payments', 'subscription.plan']);

        return $this->success(new InvoiceResource($invoice), 'Invoice retrieved successfully.');
    }

    #[Endpoint(operationId: 'billing.voidInvoice', title: 'Void invoice', description: 'Void an unpaid invoice.')]
    public function voidInvoice(Invoice $invoice): JsonResponse
    {
        $this->authorize('manageBillingInvoices');

        return $this->success(
            new InvoiceResource($this->invoiceService->void($invoice)),
            'Invoice voided successfully.'
        );
    }

    #[Endpoint(operationId: 'billing.payments', title: 'List payments', description: 'Paginate payment records.')]
    public function payments(Request $request): JsonResponse
    {
        $this->authorize('viewBillingPayments');

        $payments = $this->paymentService->paginate($request->only([
            'tenant_id', 'status', 'gateway', 'search', 'per_page',
        ]));

        return $this->paginated(PaymentResource::collection($payments), 'Payments retrieved successfully.');
    }

    #[Endpoint(operationId: 'billing.paymentStatistics', title: 'Payment statistics', description: 'Return payment overview statistics.')]
    public function paymentStatistics(): JsonResponse
    {
        $this->authorize('viewBillingPayments');

        return $this->success(
            $this->paymentService->overviewStatistics(),
            'Payment statistics retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'billing.chargeInvoice', title: 'Charge invoice', description: 'Charge an invoice through a payment gateway driver.')]
    public function chargeInvoice(ChargeInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $payment = $this->paymentService->chargeInvoice($invoice, $request->validated());
        $payment->loadMissing('attempts');

        $message = $payment->status === PaymentStatus::PROCESSING
            ? 'Payment initiated. Redirect the customer using checkout_url when present.'
            : 'Payment charged successfully.';

        return $this->success(new PaymentResource($payment), $message, 201);
    }

    #[Endpoint(operationId: 'billing.sendInvoicePaymentLink', title: 'Send invoice payment link', description: 'Email a signed public payment link for an unpaid invoice.')]
    public function sendInvoicePaymentLink(SendInvoicePaymentLinkRequest $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validated();

        $result = $this->publicInvoices->sendPaymentLink(
            $invoice,
            isset($data['email']) ? (string) $data['email'] : null,
        );

        return $this->success($result, 'Payment link sent successfully.');
    }

    #[Endpoint(operationId: 'billing.showPayment', title: 'Show payment', description: 'Return payment details.')]
    public function showPayment(Payment $payment): JsonResponse
    {
        $this->authorize('viewBillingPayments');
        $payment->load(['tenant', 'attempts', 'refunds', 'logs', 'invoice']);

        return $this->success(new PaymentResource($payment), 'Payment retrieved successfully.');
    }

    #[Endpoint(operationId: 'billing.refundPayment', title: 'Refund payment', description: 'Issue a full or partial refund.')]
    public function refundPayment(RefundPaymentRequest $request, Payment $payment): JsonResponse
    {
        $refund = $this->paymentService->refund($payment, $request->validated());

        return $this->success(new RefundResource($refund), 'Refund processed successfully.', 201);
    }

    #[Endpoint(operationId: 'billing.storeBillingAddress', title: 'Create billing address', description: 'Add a billing address for a tenant.')]
    public function storeBillingAddress(StoreBillingAddressRequest $request, Tenant $tenant): JsonResponse
    {
        $address = $this->invoiceService->upsertBillingAddress($tenant, $request->validated());

        return $this->success(new BillingAddressResource($address), 'Billing address saved successfully.', 201);
    }

    #[Endpoint(operationId: 'billing.billingProfile.show', title: 'Show billing profile', description: 'Return the tenant billing profile used for currency and gateway resolution.')]
    public function showBillingProfile(Tenant $tenant): JsonResponse
    {
        $this->authorize('viewBillingProfile');

        $profile = $this->billingProfiles->forTenant($tenant);

        return $this->success(new BillingProfileResource($profile), 'Billing profile retrieved successfully.');
    }

    #[Endpoint(operationId: 'billing.billingProfile.update', title: 'Update billing profile', description: 'Update tenant country, currency, and preferred gateway preferences.')]
    public function updateBillingProfile(UpdateBillingProfileRequest $request, Tenant $tenant): JsonResponse
    {
        $profile = $this->billingProfiles->update($tenant, $request->validated());

        return $this->success(new BillingProfileResource($profile), 'Billing profile updated successfully.');
    }

    #[Endpoint(operationId: 'billing.gateways', title: 'List gateways', description: 'List payment gateways from the database catalog when available, otherwise registered drivers.')]
    public function gateways(): JsonResponse
    {
        $this->authorize('viewBillingGateways');

        return $this->success(
            $this->gatewayCatalog->listForAdmin(),
            'Payment gateways retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'billing.gatewayOptions', title: 'Gateway options', description: 'Return payment gateway dropdown options as value/label pairs from registered drivers.')]
    public function gatewayOptions(): JsonResponse
    {
        $this->authorize('viewBillingGatewayOptions');

        return $this->success(
            $this->gatewayCatalog->options(),
            'Payment gateway options retrieved successfully.',
        );
    }
}
