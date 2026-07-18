<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Enums\Central\InvoiceStatus;
use App\Models\Central\BillingAddress;
use App\Models\Central\Invoice;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Services\Central\Settings\SettingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Service responsible for invoice creation and billing address management.
 *
 * Encapsulates subscription and manual invoice generation, status transitions,
 * and tenant billing address upserts so controllers remain thin.
 */
final class InvoiceService
{
    public function __construct(
        private readonly BillingSettings $billingSettings,
        private readonly SettingService  $settings,
    )
    {
    }

    /**
     * Paginate invoices with optional search and status filters.
     *
     * @param array{tenant_id?: string, status?: string, subscription_id?: int, search?: string, per_page?: int} $filters
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int)($filters['per_page'] ?? 15), 100);

        return Invoice::query()
            ->with(['tenant', 'subscription.plan', 'items', 'billingAddress'])
            ->when($filters['tenant_id'] ?? null, fn($q, $id) => $q->where('tenant_id', $id))
            ->when($filters['status'] ?? null, fn($q, $status) => $q->where('status', $status))
            ->when($filters['subscription_id'] ?? null, fn($q, $id) => $q->where('subscription_id', $id))
            ->when(
                $filters['search'] ?? null,
                fn($query, string $search) => $query->where(function ($q) use ($search): void {
                    $q->where('number', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhere('currency', 'like', "%{$search}%")
                        ->orWhereHas('tenant', function ($tenantQuery) use ($search): void {
                            $tenantQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('slug', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                })
            )
            ->latest()
            ->paginate($perPage);
    }

    /**
     * @return array{
     *     total: int,
     *     draft: int,
     *     open: int,
     *     paid: int,
     *     overdue: int,
     *     void: int,
     *     volume: float,
     *     by_status: array<string, int>
     * }
     */
    public function overviewStatistics(): array
    {
        $byStatus = Invoice::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn($count): int => (int)$count)
            ->all();

        return [
            'total' => (int)array_sum($byStatus),
            'draft' => (int)($byStatus[InvoiceStatus::DRAFT->value] ?? 0),
            'open' => (int)($byStatus[InvoiceStatus::OPEN->value] ?? 0),
            'paid' => (int)($byStatus[InvoiceStatus::PAID->value] ?? 0),
            'overdue' => (int)($byStatus[InvoiceStatus::OVERDUE->value] ?? 0),
            'void' => (int)($byStatus[InvoiceStatus::VOID->value] ?? 0),
            'volume' => (float)Invoice::query()->sum('total'),
            'by_status' => $byStatus,
        ];
    }

    /**
     * Create an invoice for a subscription billing period.
     *
     * Derives subtotal from the subscription price, applies optional tax,
     * and creates a single line item representing the subscription charge.
     *
     * @param Subscription $subscription
     * @param array{description?: string, billing_address_id?: int|null, tax_rate?: float|int, due_days?: int, idempotency_key?: string|null} $options
     * @return Invoice
     */
    public function createForSubscription(Subscription $subscription, array $options = []): Invoice
    {
        $subscription->loadMissing('plan');

        if (!empty($options['billing_address_id'])) {
            $addressExists = BillingAddress::query()
                ->whereKey($options['billing_address_id'])
                ->where('tenant_id', $subscription->tenant_id)
                ->exists();

            if (!$addressExists) {
                throw ValidationException::withMessages([
                    'billing_address_id' => ['The selected billing address does not belong to this subscription tenant.'],
                ]);
            }
        }

        $subtotal = (float)$subscription->price;
        $taxRate = (float)($options['tax_rate'] ?? 0);
        $tax = round($subtotal * ($taxRate / 100), 2);
        $total = round($subtotal + $tax, 2);

        return DB::transaction(function () use ($subscription, $options, $subtotal, $taxRate, $tax, $total): Invoice {
            $attributes = [
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'billing_address_id' => $options['billing_address_id'] ?? null,
                'number' => $this->nextNumber(),
                'status' => InvoiceStatus::OPEN,
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax' => $tax,
                'total' => $total,
                'amount_paid' => 0,
                'currency' => $subscription->currency,
                'issued_at' => now(),
                'due_at' => now()->addDays((int)($options['due_days'] ?? $this->billingSettings->invoiceDueDays())),
            ];
            $idempotencyKey = filled($options['idempotency_key'] ?? null)
                ? (string)$options['idempotency_key']
                : null;

            $invoice = $idempotencyKey === null
                ? Invoice::query()->create($attributes)
                : Invoice::query()->firstOrCreate(
                    ['idempotency_key' => $idempotencyKey],
                    $attributes,
                );

            if (!$invoice->wasRecentlyCreated) {
                return $invoice->load(['items', 'subscription.plan', 'billingAddress']);
            }

            $invoice->items()->create([
                'description' => $options['description'] ?? ($subscription->plan?->name . ' subscription'),
                'quantity' => 1,
                'unit_price' => $subtotal,
                'total' => $subtotal,
            ]);

            return $invoice->load(['items', 'subscription.plan', 'billingAddress']);
        });
    }

    /**
     * Generate a unique invoice number for the current day.
     *
     * @return string
     */
    private function nextNumber(): string
    {
        return $this->billingSettings->invoiceNumberPrefix() . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
    }

    /**
     * Create a manual invoice with custom line items.
     *
     * Calculates subtotal, tax, and total from the provided items and rejects
     * requests that do not include at least one line item.
     *
     * @param array{tenant_id: string, subscription_id?: int|null, billing_address_id?: int|null, tax_rate?: float, currency?: string, items: list<array{description: string, quantity?: int, unit_price: float|int}>, notes?: string|null} $data
     * @return Invoice
     *
     * @throws ValidationException
     */
    public function create(array $data): Invoice
    {
        return DB::transaction(function () use ($data): Invoice {
            $items = $data['items'] ?? [];

            if ($items === []) {
                throw ValidationException::withMessages([
                    'items' => ['At least one invoice item is required.'],
                ]);
            }

            Tenant::query()->lockForUpdate()->findOrFail($data['tenant_id']);

            if (!empty($data['subscription_id'])) {
                $subscriptionExists = Subscription::query()
                    ->whereKey($data['subscription_id'])
                    ->where('tenant_id', $data['tenant_id'])
                    ->lockForUpdate()
                    ->exists();

                if (!$subscriptionExists) {
                    throw ValidationException::withMessages([
                        'subscription_id' => ['The selected subscription does not belong to this tenant.'],
                    ]);
                }
            }

            if (!empty($data['billing_address_id'])) {
                $addressExists = BillingAddress::query()
                    ->whereKey($data['billing_address_id'])
                    ->where('tenant_id', $data['tenant_id'])
                    ->exists();

                if (!$addressExists) {
                    throw ValidationException::withMessages([
                        'billing_address_id' => ['The selected billing address does not belong to this tenant.'],
                    ]);
                }
            }

            $subtotal = collect($items)->sum(
                fn(array $item): float => ((int)($item['quantity'] ?? 1)) * (float)$item['unit_price']
            );
            $taxRate = (float)($data['tax_rate'] ?? 0);
            $tax = round($subtotal * ($taxRate / 100), 2);
            $total = round($subtotal + $tax, 2);

            $currency = $data['currency'] ?? null;

            if ($currency === null && !empty($data['subscription_id'])) {
                $currency = Subscription::query()->whereKey($data['subscription_id'])->value('currency');
            }

            if ($currency === null) {
                $currency = (string)$this->settings->get(
                    'billing.default_currency',
                    config('payments.currency', 'USD'),
                );
            }

            $invoice = Invoice::query()->create([
                'tenant_id' => $data['tenant_id'],
                'subscription_id' => $data['subscription_id'] ?? null,
                'billing_address_id' => $data['billing_address_id'] ?? null,
                'number' => $this->nextNumber(),
                'status' => InvoiceStatus::OPEN,
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax' => $tax,
                'total' => $total,
                'amount_paid' => 0,
                'currency' => $currency,
                'issued_at' => now(),
                'due_at' => now()->addDays($this->billingSettings->invoiceDueDays()),
                'notes' => $data['notes'] ?? null,
                'tax_id' => $data['tax_id'] ?? null,
            ]);

            foreach ($items as $item) {
                $qty = (int)($item['quantity'] ?? 1);
                $unit = (float)$item['unit_price'];
                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'total' => round($qty * $unit, 2),
                ]);
            }

            return $invoice->load(['items', 'tenant', 'billingAddress']);
        });
    }

    /**
     * Void an open or overdue invoice.
     *
     * @param Invoice $invoice
     * @return Invoice
     *
     * @throws ValidationException
     */
    public function void(Invoice $invoice): Invoice
    {
        if ($invoice->status === InvoiceStatus::PAID) {
            throw ValidationException::withMessages([
                'invoice' => ['Paid invoices cannot be voided.'],
            ]);
        }

        $invoice->update(['status' => InvoiceStatus::VOID]);

        return $invoice->fresh(['items']);
    }

    /**
     * Mark an open invoice as overdue when its due date has passed.
     *
     * @param Invoice $invoice
     * @return Invoice
     */
    public function markOverdue(Invoice $invoice): Invoice
    {
        if ($invoice->status === InvoiceStatus::OPEN && $invoice->due_at?->isPast()) {
            $invoice->update(['status' => InvoiceStatus::OVERDUE]);
        }

        return $invoice->fresh();
    }

    /**
     * Create or update a tenant billing address.
     *
     * Clears other default addresses when the incoming record is marked as
     * default and supports updates when an existing address ID is provided.
     *
     * @param Tenant $tenant
     * @param array<string, mixed> $data
     * @return BillingAddress
     */
    public function upsertBillingAddress(Tenant $tenant, array $data): BillingAddress
    {
        return DB::transaction(function () use ($tenant, $data): BillingAddress {
            if (($data['is_default'] ?? false) === true) {
                $tenant->billingAddresses()->update(['is_default' => false]);
            }

            if (!empty($data['id'])) {
                $address = $tenant->billingAddresses()->whereKey($data['id'])->firstOrFail();
                $address->update($data);

                return $address->fresh();
            }

            return $tenant->billingAddresses()->create($data);
        });
    }
}
