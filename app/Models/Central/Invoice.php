<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\InvoiceStatus;
use Database\Factories\Central\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Billing invoice issued to a tenant.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int|null $subscription_id
 * @property string|null $idempotency_key
 * @property int|null $billing_address_id
 * @property string $number
 * @property InvoiceStatus $status
 * @property string $subtotal
 * @property string $tax_rate
 * @property string $tax
 * @property string $total
 * @property string $amount_paid
 * @property string $currency
 * @property string|null $tax_id
 * @property Carbon|null $issued_at
 * @property Carbon|null $due_at
 * @property Carbon|null $paid_at
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read Subscription|null $subscription
 * @property-read BillingAddress|null $billingAddress
 * @property-read Collection<int, InvoiceItem> $items
 * @property-read Collection<int, Payment> $payments
 *
 * @method static Builder<static> query()
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use CentralConnection, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'idempotency_key',
        'billing_address_id',
        'number',
        'status',
        'subtotal',
        'tax_rate',
        'tax',
        'total',
        'amount_paid',
        'currency',
        'tax_id',
        'issued_at',
        'due_at',
        'paid_at',
        'notes',
        'metadata',
    ];

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }

    /**
     * Tenant billed by this invoice.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Subscription that generated this invoice, if any.
     *
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Billing address snapshot used for this invoice.
     *
     * @return BelongsTo<BillingAddress, $this>
     */
    public function billingAddress(): BelongsTo
    {
        return $this->belongsTo(BillingAddress::class);
    }

    /**
     * Line items on this invoice.
     *
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Payments applied to this invoice.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Calculate the remaining balance due on the invoice.
     */
    public function balanceDue(): float
    {
        return max(0, (float) $this->total - (float) $this->amount_paid);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
