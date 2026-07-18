<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\AnnouncementStatus;
use App\Enums\Central\AnnouncementTarget;
use App\Enums\Central\AnnouncementType;
use App\Models\Central\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Announcement> */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(5),
            'body' => fake()->paragraph(),
            'type' => AnnouncementType::SYSTEM_NOTICE,
            'target' => AnnouncementTarget::ALL_TENANTS,
            'status' => AnnouncementStatus::Draft,
            'is_dismissible' => true,
            'metadata' => [],
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => AnnouncementStatus::Published,
            'published_at' => now(),
            'starts_at' => now()->subHour(),
        ]);
    }
}
