<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\PlanStatus;
use App\Enums\Central\SubscriptionInterval;
use App\Models\Central\Plan;
use App\Models\Central\PlanPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanPrice>
 */
class PlanPriceFactory extends Factory
{
    protected $model = PlanPrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'amount' => fake()->randomFloat(2, 9, 299),
            'currency' => 'USD',
            'billing_interval' => SubscriptionInterval::MONTHLY,
            'trial_days' => null,
            'status' => PlanStatus::Active,
            'metadata' => [],
        ];
    }
}
