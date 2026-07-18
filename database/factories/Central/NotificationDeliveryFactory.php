<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\DeliveryStatus;
use App\Enums\Central\NotificationChannel;
use App\Models\Central\NotificationDelivery;
use App\Models\Central\PlatformNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NotificationDelivery> */
class NotificationDeliveryFactory extends Factory
{
    protected $model = NotificationDelivery::class;

    public function definition(): array
    {
        return [
            'platform_notification_id' => PlatformNotification::factory(),
            'channel' => NotificationChannel::IN_APP,
            'status' => DeliveryStatus::Delivered,
            'delivered_at' => now(),
        ];
    }
}
