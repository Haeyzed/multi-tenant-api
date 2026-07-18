<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Central\Feature;
use App\Models\Central\FeatureUsage;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeatureUsage>
 */
class FeatureUsageFactory extends Factory
{
    protected $model = FeatureUsage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'feature_id' => Feature::factory(),
            'plan_id' => Plan::factory(),
            'used' => fake()->numberBetween(0, 50),
            'period_starts_at' => now()->startOfMonth(),
            'period_ends_at' => now()->endOfMonth(),
        ];
    }
}
