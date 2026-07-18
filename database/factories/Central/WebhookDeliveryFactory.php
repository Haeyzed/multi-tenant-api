<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\WebhookEvent;
use App\Enums\Central\WebhookStatus;
use App\Models\Central\Webhook;
use App\Models\Central\WebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WebhookDelivery> */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    public function definition(): array
    {
        return [
            'webhook_id' => Webhook::factory(),
            'event' => WebhookEvent::TENANT_CREATED,
            'status' => WebhookStatus::PENDING,
            'attempt' => 1,
            'payload' => json_encode(['id' => 1]),
        ];
    }
}
