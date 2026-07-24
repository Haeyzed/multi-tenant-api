<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\PlanStatus;
use App\Enums\Central\SubscriptionInterval;
use Database\Factories\Central\PlanPriceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Explicit price for a plan in a given currency and billing interval.
 *
 * @property int $id
 * @property int $plan_id
 * @property string $amount
 * @property string $currency
 * @property SubscriptionInterval $billing_interval
 * @property int|null $trial_days
 * @property PlanStatus $status
 * @property array<string, mixed>|null $metadata
 * @property array<string, mixed>|null $gateway_identifiers
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Plan $plan
 *
 * @method static Builder<static> query()
 */
class PlanPrice extends Model
{
    /** @use HasFactory<PlanPriceFactory> */
    use CentralConnection;

    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plan_id',
        'amount',
        'currency',
        'billing_interval',
        'trial_days',
        'status',
        'metadata',
        'gateway_identifiers',
    ];

    protected static function newFactory(): PlanPriceFactory
    {
        return PlanPriceFactory::new();
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive(): bool
    {
        return $this->status === PlanStatus::Active;
    }

    /**
     * Effective trial days for this price (falls back to plan).
     */
    public function effectiveTrialDays(): int
    {
        if ($this->trial_days !== null) {
            return max(0, (int) $this->trial_days);
        }

        return max(0, (int) ($this->plan?->trial_days ?? 0));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'billing_interval' => SubscriptionInterval::class,
            'trial_days' => 'integer',
            'status' => PlanStatus::class,
            'metadata' => 'array',
            'gateway_identifiers' => 'array',
        ];
    }
}
