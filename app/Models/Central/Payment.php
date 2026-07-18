<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\PaymentGateway;
use App\Enums\Central\PaymentStatus;
use Database\Factories\Central\PaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Payment attempt or settlement against a tenant invoice or subscription.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int|null $invoice_id
 * @property int|null $subscription_id
 * @property PaymentGateway $gateway
 * @property PaymentStatus $status
 * @property string $amount
 * @property string $currency
 * @property string|null $gateway_reference
 * @property string|null $failure_reason
 * @property Carbon|null $paid_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read Invoice|null $invoice
 * @property-read Subscription|null $subscription
 * @property-read Collection<int, PaymentAttempt> $attempts
 * @property-read Collection<int, PaymentLog> $logs
 * @property-read Collection<int, Refund> $refunds
 *
 * @method static Builder<static> query()
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use CentralConnection, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'subscription_id',
        'gateway',
        'status',
        'amount',
        'currency',
        'gateway_reference',
        'failure_reason',
        'paid_at',
        'metadata',
    ];

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }

    /**
     * Tenant associated with this payment.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Invoice this payment applies to, if any.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Subscription this payment applies to, if any.
     *
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Gateway retry attempts for this payment.
     *
     * @return HasMany<PaymentAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    /**
     * Diagnostic logs emitted during payment processing.
     *
     * @return HasMany<PaymentLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(PaymentLog::class);
    }

    /**
     * Refunds issued against this payment.
     *
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Sum refunded amounts for completed or refunded statuses.
     */
    public function refundedAmount(): float
    {
        return (float) $this->refunds()
            ->whereIn('status', [PaymentStatus::COMPLETED->value, PaymentStatus::REFUNDED->value])
            ->sum('amount');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gateway' => PaymentGateway::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
