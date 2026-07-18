<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\PaymentStatus;
use App\Models\Central\Payment;
use App\Models\Central\Refund;
use App\Models\Central\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Refund> */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'tenant_id' => Tenant::factory(),
            'amount' => 10,
            'currency' => 'NGN',
            'status' => PaymentStatus::PENDING,
            'reason' => 'requested_by_customer',
        ];
    }
}
