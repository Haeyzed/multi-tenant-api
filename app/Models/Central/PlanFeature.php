<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\PlanFeatureLimitType;
use App\Enums\Central\SubscriptionInterval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Pivot linking a plan to a feature with limit configuration.
 *
 * @property int $id
 * @property int $plan_id
 * @property int $feature_id
 * @property PlanFeatureLimitType|null $limit_type
 * @property int|null $limit_value
 * @property bool $is_unlimited
 * @property bool $is_enabled
 * @property bool $tracks_usage
 * @property SubscriptionInterval|null $reset_period
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Plan $plan
 * @property-read Feature $feature
 *
 * @method static Builder<static> query()
 */
class PlanFeature extends Pivot
{
    public $incrementing = true;

    protected $table = 'plan_feature';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plan_id',
        'feature_id',
        'limit_type',
        'limit_value',
        'is_unlimited',
        'is_enabled',
        'tracks_usage',
        'reset_period',
        'metadata',
    ];

    /**
     * Plan side of the pivot.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Feature side of the pivot.
     *
     * @return BelongsTo<Feature, $this>
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    /**
     * Determine whether usage is unlimited for this plan feature.
     */
    public function allowsUnlimited(): bool
    {
        return $this->is_unlimited || $this->limit_type === PlanFeatureLimitType::UNLIMITED;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'limit_type' => PlanFeatureLimitType::class,
            'limit_value' => 'integer',
            'is_unlimited' => 'boolean',
            'is_enabled' => 'boolean',
            'tracks_usage' => 'boolean',
            'reset_period' => SubscriptionInterval::class,
            'metadata' => 'array',
        ];
    }
}
