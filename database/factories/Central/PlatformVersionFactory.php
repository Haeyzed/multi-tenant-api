<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\PlatformVersionStatus;
use App\Models\Central\PlatformVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlatformVersion> */
class PlatformVersionFactory extends Factory
{
    protected $model = PlatformVersion::class;

    public function definition(): array
    {
        return [
            'version' => fake()->unique()->numerify('#.#.#'),
            'status' => PlatformVersionStatus::Draft,
            'release_notes' => fake()->paragraph(),
            'is_current' => false,
            'migration_status' => ['pending' => 0, 'ran' => 0],
            'metadata' => [],
        ];
    }
}
