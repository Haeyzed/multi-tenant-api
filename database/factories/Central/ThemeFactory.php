<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\ThemeStatus;
use App\Models\Central\Theme;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Theme> */
class ThemeFactory extends Factory
{
    protected $model = Theme::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => fake()->sentence(),
            'version' => '1.0.0',
            'status' => ThemeStatus::PUBLISHED,
            'preview_url' => fake()->url(),
            'price' => 0,
            'author' => fake()->name(),
            'metadata' => [],
        ];
    }
}
