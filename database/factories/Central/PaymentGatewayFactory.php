<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Central\PaymentGateway;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentGateway>
 */
class PaymentGatewayFactory extends Factory
{
    protected $model = PaymentGateway::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = Str::lower(fake()->unique()->lexify('gateway_????'));

        return [
            'name' => Str::title(str_replace('_', ' ', $slug)),
            'slug' => $slug,
            'driver' => $slug,
            'priority' => 100,
            'is_active' => true,
            'is_fallback' => false,
            'supports_subscription' => true,
            'supports_refund' => true,
            'supports_webhook' => true,
            'supports_partial_refund' => true,
            'config' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function fallback(): static
    {
        return $this->state(fn (): array => ['is_fallback' => true]);
    }
}
