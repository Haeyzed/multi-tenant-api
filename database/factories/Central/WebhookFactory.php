<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\WebhookEvent;
use App\Models\Central\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Webhook> */
class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'url' => fake()->url(),
            'secret' => 'whsec_'.Str::random(32),
            'events' => [WebhookEvent::TENANT_CREATED->value, WebhookEvent::PAYMENT_SUCCEEDED->value],
            'is_active' => true,
            'max_retries' => 3,
            'timeout_seconds' => 10,
            'metadata' => [],
        ];
    }
}
