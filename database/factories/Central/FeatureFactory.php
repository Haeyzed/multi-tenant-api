<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\FeatureStatus;
use App\Enums\Central\PlanFeatureLimitType;
use App\Models\Central\Feature;
use App\Models\Central\FeatureCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Feature>
 */
class FeatureFactory extends Factory
{
    protected $model = Feature::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        $slug = Str::slug($name).'-'.Str::lower(Str::random(4));

        return [
            'feature_category_id' => FeatureCategory::factory(),
            'name' => Str::title($name),
            'slug' => $slug,
            'key' => Str::snake($slug),
            'description' => fake()->sentence(),
            'icon' => 'sparkles',
            'status' => FeatureStatus::Active,
            'default_limit_type' => PlanFeatureLimitType::BOOLEAN,
            'default_limit_value' => null,
            'unit' => null,
            'is_available' => true,
            'tracks_usage' => false,
            'sort_order' => 0,
            'metadata' => [],
        ];
    }

    public function countable(): static
    {
        return $this->state(fn (): array => [
            'default_limit_type' => PlanFeatureLimitType::COUNT,
            'default_limit_value' => 100,
            'unit' => 'items',
            'tracks_usage' => true,
        ]);
    }
}
