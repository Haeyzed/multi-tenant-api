<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->optional()->paragraph(),
            'summary' => fake()->optional()->sentence(),
            'is_visible' => true,
            'is_featured' => false,
            'logo_media_id' => null,
            'banner_media_id' => null,
            'meta_title' => null,
            'meta_description' => null,
            'website_url' => fake()->optional()->url(),
            'country_of_origin' => fake()->optional()->countryCode(),
            'sort_order' => 0,
        ];
    }
}
