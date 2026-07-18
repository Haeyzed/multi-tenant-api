<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Billing;

use App\Enums\Central\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Http\Resources\Central\BillingAddressResource;
use App\Http\Resources\Central\InvoiceResource;
use App\Http\Resources\Central\PaymentResource;
use App\Http\Resources\Central\RefundResource;
use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\Tenant;
use App\Payments\PaymentGatewayManager;
use App\Services\Central\Billing\InvoiceService;
use App\Services\Central\Billing\PaymentService;
use App\Services\Central\Billing\PublicInvoicePaymentService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

#[Group('Central Billing', description: 'Invoices, payments, refunds, addresses, gateways.', weight: 140)]
final class BillingController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly PaymentService $paymentService,
        private readonly PaymentGatewayManager $gatewayManager,
        private readonly PublicInvoicePaymentService $publicInvoices,
    ) {}

    #[Endpoint(operationId: 'billing.invoices', title: 'List invoices', description: 'Paginate invoices with optional filters.')]
    public function invoices(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('billing.invoices.view'), 403);

        $invoices = $this->invoiceService->paginate($request->only([
            'tenant_id', 'status', 'subscription_id', 'search', 'per_page',
        ]));

        return $this->paginated(InvoiceResource::collection($invoices), 'Invoices retrieved successfully.');
    }

    #[Endpoint(operationId: 'billing.invoiceStatistics', title: 'Invoice statistics', description: 'Return invoice overview statistics.')]
    public function invoiceStatistics(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('billing.invoices.view'), 403);

        return $this->success(
            $this->invoiceService->overviewStatistics(),
            'Invoice statistics retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'billing.storeInvoice', title: 'Create invoice', description: 'Create an invoice for a tenant/subscription.')]
    public function storeInvoice(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('billing.invoices.manage'), 403);

        $data = $request->validate([
            'tenant_id' => ['required', 'string', 'exists:tenants,id'],
            'subscription_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('subscriptions', 'id')
                    ->where(fn ($query) => $query->where('tenant_id', $request->input('tenant_id'))),
            ],
            'billing_address_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('billing_addresses', 'id')
                    ->where(fn ($query) => $query->where('tenant_id', $request->input('tenant_id'))),
            ],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'tax_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['sometimes', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $invoice = $this->invoiceService->create($data);

        return $this->success(new InvoiceResource($invoice), 'Invoice created successfully.', 201);
    }

    #[Endpoint(operationId: 'billing.showInvoice', title: 'Show invoice', description: 'Return invoice details including line items.')]
    public function showInvoice(Invoice $invoice): JsonResponse
    {
        abort_unless(request()->user()?->can('billing.invoices.view'), 403);
        $invoice->load(['tenant', 'items', 'billingAddress', 'payments', 'subscription.plan']);

        return $this->success(new InvoiceResource($invoice), 'Invoice retrieved successfully.');
    }

    #[Endpoint(operationId: 'billing.voidInvoice', title: 'Void invoice', description: 'Void an unpaid invoice.')]
    public function voidInvoice(Invoice $invoice): JsonResponse
    {
        abort_unless(request()->user()?->can('billing.invoices.manage'), 403);

        return $this->success(
            new InvoiceResource($this->invoiceService->void($invoice)),
            'Invoice voided successfully.'
        );
    }

    #[Endpoint(operationId: 'billing.payments', title: 'List payments', description: 'Paginate payment records.')]
    public function payments(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('billing.payments.view'), 403);

        $payments = $this->paymentService->paginate($request->only([
            'tenant_id', 'status', 'gateway', 'search', 'per_page',
        ]));

        return $this->paginated(PaymentResource::collection($payments), 'Payments retrieved successfully.');
    }

    #[Endpoint(operationId: 'billing.paymentStatistics', title: 'Payment statistics', description: 'Return payment overview statistics.')]
    public function paymentStatistics(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('billing.payments.view'), 403);

        return $this->success(
            $this->paymentService->overviewStatistics(),
            'Payment statistics retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'billing.chargeInvoice', title: 'Charge invoice', description: 'Charge an invoice through a payment gateway driver.')]
    public function chargeInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($request->user()?->can('billing.payments.charge'), 403);

        $data = $request->validate([
            'gateway' => ['sometimes', 'string'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'force_failure' => ['sometimes', 'boolean'],
            'payment_method' => ['sometimes', 'string'],
            'authorization_code' => ['sometimes', 'string'],
        ]);

        $payment = $this->paymentService->chargeInvoice($invoice, $data);
        $payment->loadMissing('attempts');

        $message = $payment->status?->value === 'processing'
            ? 'Payment initiated. Redirect the customer using checkout_url when present.'
            : 'Payment charged successfully.';

        return $this->success(new PaymentResource($payment), $message, 201);
    }

    #[Endpoint(operationId: 'billing.sendInvoicePaymentLink', title: 'Send invoice payment link', description: 'Email a signed public payment link for an unpaid invoice.')]
    public function sendInvoicePaymentLink(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($request->user()?->can('billing.invoices.manage'), 403);

        $data = $request->validate([
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
        ]);

        $result = $this->publicInvoices->sendPaymentLink(
            $invoice,
            isset($data['email']) ? (string) $data['email'] : null,
        );

        return $this->success($result, 'Payment link sent successfully.');
    }

    #[Endpoint(operationId: 'billing.showPayment', title: 'Show payment', description: 'Return payment details.')]
    public function showPayment(Payment $payment): JsonResponse
    {
        abort_unless(request()->user()?->can('billing.payments.view'), 403);
        $payment->load(['tenant', 'attempts', 'refunds', 'logs', 'invoice']);

        return $this->success(new PaymentResource($payment), 'Payment retrieved successfully.');
    }

    #[Endpoint(operationId: 'billing.refundPayment', title: 'Refund payment', description: 'Issue a full or partial refund.')]
    public function refundPayment(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($request->user()?->can('billing.payments.refund'), 403);

        $data = $request->validate([
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $refund = $this->paymentService->refund($payment, $data);

        return $this->success(new RefundResource($refund), 'Refund processed successfully.', 201);
    }

    #[Endpoint(operationId: 'billing.storeBillingAddress', title: 'Create billing address', description: 'Add a billing address for a tenant.')]
    public function storeBillingAddress(Request $request, Tenant $tenant): JsonResponse
    {
        abort_unless($request->user()?->can('billing.addresses.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['sometimes', 'nullable', 'string', 'max:255'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:50'],
            'country' => ['required', 'string', 'size:2'],
            'tax_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'tax_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $address = $this->invoiceService->upsertBillingAddress($tenant, $data);

        return $this->success(new BillingAddressResource($address), 'Billing address saved successfully.', 201);
    }

    #[Endpoint(operationId: 'billing.gateways', title: 'List gateways', description: 'List available payment gateway drivers.')]
    public function gateways(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('billing.gateways.view'), 403);

        $gateways = collect($this->gatewayManager->available())->map(function (string $name) {
            $driver = $this->gatewayManager->driver($name);

            return [
                'name' => $name,
                'supports_refunds' => $driver->supportsRefunds(),
                'supports_recurring' => $driver->supportsRecurring(),
            ];
        })->values();

        return $this->success($gateways, 'Payment gateways retrieved successfully.');
    }

    #[Endpoint(operationId: 'billing.gatewayOptions', title: 'Gateway options', description: 'Return payment gateway dropdown options as value/label pairs from the PaymentGateway enum (registered drivers only).')]
    public function gatewayOptions(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()?->can('billing.gateways.view')
                || $request->user()?->can('billing.payments.charge'),
            403
        );

        $options = collect($this->gatewayManager->available())
            ->map(function (string $name): array {
                $gateway = PaymentGateway::tryFrom($name);

                return [
                    'value' => $name,
                    'label' => $gateway?->label() ?? Str::headline(str_replace('_', ' ', $name)),
                ];
            })
            ->values()
            ->all();

        return $this->success($options, 'Payment gateway options retrieved successfully.');
    }
}

