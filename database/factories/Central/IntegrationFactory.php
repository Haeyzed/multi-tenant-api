<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\IntegrationStatus;
use App\Models\Central\Integration;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Integration> */
class IntegrationFactory extends Factory
{
    protected $model = Integration::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'vendor' => fake()->company(),
            'description' => fake()->sentence(),
            'version' => '1.0.0',
            'status' => IntegrationStatus::ACTIVE,
            'is_marketplace' => true,
            'price' => 0,
            'permissions' => ['read'],
            'config_schema' => [],
            'metadata' => [],
        ];
    }
}
