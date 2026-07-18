<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\PaymentGateway;
use App\Enums\Central\PaymentMethodStatus;
use App\Models\Central\PaymentMethod;
use App\Models\Central\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentMethod> */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'gateway' => PaymentGateway::PAYSTACK,
            'status' => PaymentMethodStatus::Active,
            'external_id' => null,
            'customer_external_id' => null,
            'authorization_code' => 'AUTH_'.fake()->bothify('????####'),
            'brand' => 'visa',
            'last_four' => '4081',
            'exp_month' => 12,
            'exp_year' => (int) now()->addYear()->format('Y'),
            'is_default' => true,
            'meta' => [],
        ];
    }
}
