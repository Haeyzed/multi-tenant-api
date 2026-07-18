<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\PaymentStatus;
use Database\Factories\Central\RefundFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Refund issued against a tenant payment.
 *
 * @property int $id
 * @property int $payment_id
 * @property string $tenant_id
 * @property string $amount
 * @property string $currency
 * @property PaymentStatus $status
 * @property string|null $gateway_reference
 * @property string|null $reason
 * @property Carbon|null $refunded_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Payment $payment
 * @property-read Tenant $tenant
 *
 * @method static Builder<static> query()
 */
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use CentralConnection, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payment_id',
        'tenant_id',
        'amount',
        'currency',
        'status',
        'gateway_reference',
        'reason',
        'refunded_at',
        'metadata',
    ];

    protected static function newFactory(): RefundFactory
    {
        return RefundFactory::new();
    }

    /**
     * Payment being refunded.
     *
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Tenant receiving the refund.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PaymentStatus::class,
            'refunded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
