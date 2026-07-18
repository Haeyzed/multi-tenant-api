<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\FeatureStatus;
use App\Enums\Central\PlanFeatureLimitType;
use Database\Factories\Central\FeatureFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Platform capability or entitlement definition.
 *
 * Features are grouped by category and attached to plans with usage limits.
 *
 * @property int $id
 * @property int|null $feature_category_id
 * @property string $name
 * @property string $slug
 * @property string $key
 * @property string|null $description
 * @property string|null $icon
 * @property FeatureStatus $status
 * @property PlanFeatureLimitType|null $default_limit_type
 * @property int|null $default_limit_value
 * @property string|null $unit
 * @property bool $is_available
 * @property bool $tracks_usage
 * @property int $sort_order
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read FeatureCategory|null $category
 * @property-read Collection<int, Plan> $plans
 * @property-read Collection<int, FeatureUsage> $usages
 *
 * @method static Builder<static> query()
 */
class Feature extends Model
{
    /** @use HasFactory<FeatureFactory> */
    use CentralConnection;

    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'feature_category_id',
        'name',
        'slug',
        'key',
        'description',
        'icon',
        'status',
        'default_limit_type',
        'default_limit_value',
        'unit',
        'is_available',
        'tracks_usage',
        'sort_order',
        'metadata',
    ];

    protected static function newFactory(): FeatureFactory
    {
        return FeatureFactory::new();
    }

    /**
     * Category that groups this feature.
     *
     * @return BelongsTo<FeatureCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FeatureCategory::class, 'feature_category_id');
    }

    /**
     * Plans that include this feature.
     *
     * @return BelongsToMany<Plan, $this, PlanFeature>
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_feature')
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

    /**
     * Usage records tracked against this feature.
     *
     * @return HasMany<FeatureUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(FeatureUsage::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FeatureStatus::class,
            'default_limit_type' => PlanFeatureLimitType::class,
            'default_limit_value' => 'integer',
            'is_available' => 'boolean',
            'tracks_usage' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }
}
