<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\NotificationChannel;
use App\Enums\Central\NotificationStatus;
use App\Models\Central\PlatformNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlatformNotification> */
class PlatformNotificationFactory extends Factory
{
    protected $model = PlatformNotification::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'channels' => [NotificationChannel::IN_APP->value, NotificationChannel::EMAIL->value],
            'status' => NotificationStatus::Draft,
            'metadata' => [],
        ];
    }
}
