<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\PaymentGateway;
use App\Enums\Central\SubscriptionInterval;
use App\Enums\Central\SubscriptionStatus;
use Database\Factories\Central\SubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Tenant subscription to a central billing plan.
 *
 * Tracks billing periods, gateway references, and lifecycle transitions.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $plan_id
 * @property int|null $plan_price_id
 * @property SubscriptionStatus $status
 * @property SubscriptionInterval $billing_interval
 * @property string $price
 * @property string $currency
 * @property PaymentGateway|null $gateway
 * @property string|null $gateway_subscription_id
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property Carbon|null $paused_at
 * @property Carbon|null $cancelled_at
 * @property bool $cancel_at_period_end
 * @property Carbon|null $expired_at
 * @property Carbon|null $grace_ends_at
 * @property string|null $cancellation_reason
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 * @property-read Plan $plan
 * @property-read PlanPrice|null $planPrice
 * @property-read Collection<int, SubscriptionHistory> $histories
 * @property-read Collection<int, Invoice> $invoices
 * @property-read Collection<int, Payment> $payments
 *
 * @method static Builder<static> query()
 */
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use CentralConnection, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'plan_price_id',
        'status',
        'billing_interval',
        'price',
        'currency',
        'gateway',
        'gateway_subscription_id',
        'default_payment_method_id',
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'current_period_start',
        'current_period_end',
        'paused_at',
        'cancelled_at',
        'cancel_at_period_end',
        'expired_at',
        'grace_ends_at',
        'cancellation_reason',
        'metadata',
    ];

    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }

    /**
     * Tenant that owns this subscription.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Plan subscribed to by the tenant.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Snapshot price row used when the subscription was created or last changed.
     *
     * @return BelongsTo<PlanPrice, $this>
     */
    public function planPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class);
    }

    /**
     * Audit history of subscription changes.
     *
     * @return HasMany<SubscriptionHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(SubscriptionHistory::class);
    }

    /**
     * Invoices generated for this subscription.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Payments collected for this subscription.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Default stored payment method for off-session charges.
     *
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function defaultPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'default_payment_method_id');
    }

    /**
     * Determine whether the subscription is within its grace period.
     */
    public function isInGracePeriod(): bool
    {
        return $this->grace_ends_at !== null && $this->grace_ends_at->isFuture();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'billing_interval' => SubscriptionInterval::class,
            'gateway' => PaymentGateway::class,
            'price' => 'decimal:2',
            'trial_ends_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'paused_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'expired_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
