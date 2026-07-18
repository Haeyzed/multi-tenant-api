<?php

declare(strict_types=1);

namespace App\Models\Central;

use Database\Factories\Central\FeatureUsageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Metered usage of a feature by a tenant within a billing period.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $feature_id
 * @property int|null $plan_id
 * @property int $used
 * @property Carbon|null $period_starts_at
 * @property Carbon|null $period_ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read Feature $feature
 * @property-read Plan|null $plan
 *
 * @method static Builder<static> query()
 */
class FeatureUsage extends Model
{
    /** @use HasFactory<FeatureUsageFactory> */
    use CentralConnection;

    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'feature_id',
        'plan_id',
        'used',
        'period_starts_at',
        'period_ends_at',
    ];

    protected static function newFactory(): FeatureUsageFactory
    {
        return FeatureUsageFactory::new();
    }

    /**
     * Tenant consuming this feature usage.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Feature being metered.
     *
     * @return BelongsTo<Feature, $this>
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    /**
     * Plan context for this usage period, if applicable.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'used' => 'integer',
            'period_starts_at' => 'datetime',
            'period_ends_at' => 'datetime',
        ];
    }
}
