<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\PaymentGateway;
use App\Enums\Central\PaymentStatus;
use App\Models\Central\Payment;
use App\Models\Central\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'gateway' => PaymentGateway::STRIPE,
            'status' => PaymentStatus::PENDING,
            'amount' => 49.99,
            'currency' => 'NGN',
        ];
    }
}
