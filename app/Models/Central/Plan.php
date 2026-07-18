<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\PlanStatus;
use App\Enums\Central\PlanVisibility;
use App\Enums\Central\SubscriptionInterval;
use Database\Factories\Central\PlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Subscription plan offered on the central platform.
 *
 * Defines pricing, billing cadence, visibility, and linked feature entitlements.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $price
 * @property string $currency
 * @property SubscriptionInterval $billing_interval
 * @property int $trial_days
 * @property PlanStatus $status
 * @property PlanVisibility $visibility
 * @property bool $is_featured
 * @property int $sort_order
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Feature> $features
 * @property-read Collection<int, PlanPrice> $prices
 *
 * @method static Builder<static> query()
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use CentralConnection;

    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'billing_interval',
        'trial_days',
        'status',
        'visibility',
        'is_featured',
        'sort_order',
        'metadata',
    ];

    protected static function newFactory(): PlanFactory
    {
        return PlanFactory::new();
    }

    /**
     * Features included in this plan with pivot limits.
     *
     * @return BelongsToMany<Feature, $this, PlanFeature>
     */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_feature')
            ->using(PlanFeature::class)
            ->withPivot([
                'id',
                'limit_type',
                'limit_value',
                'is_unlimited',
                'is_enabled',
                'tracks_usage',
                'reset_period',
                'metadata',
            ])
            ->withTimestamps();
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class)->orderBy('currency');
    }

    /**
     * Determine whether the plan is active and publicly visible.
     */
    public function isPubliclyVisible(): bool
    {
        return $this->status === PlanStatus::Active
            && $this->visibility === PlanVisibility::Public;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'billing_interval' => SubscriptionInterval::class,
            'trial_days' => 'integer',
            'status' => PlanStatus::class,
            'visibility' => PlanVisibility::class,
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }
}
