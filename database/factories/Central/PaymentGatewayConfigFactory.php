<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Central\PaymentGateway;
use App\Models\Central\PaymentGatewayConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentGatewayConfig>
 */
class PaymentGatewayConfigFactory extends Factory
{
    protected $model = PaymentGatewayConfig::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_gateway_id' => PaymentGateway::factory(),
            'environment' => 'test',
            'public_key' => 'pk_test_'.fake()->bothify('????????'),
            'secret_key' => 'sk_test_'.fake()->bothify('????????'),
            'webhook_secret' => 'whsec_'.fake()->bothify('????????'),
            'is_active' => true,
        ];
    }
}
