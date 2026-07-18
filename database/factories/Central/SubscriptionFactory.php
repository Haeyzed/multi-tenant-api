<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\PaymentGateway;
use App\Enums\Central\SubscriptionInterval;
use App\Enums\Central\SubscriptionStatus;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subscription> */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $start = now()->startOfDay();

        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => Plan::factory(),
            'status' => SubscriptionStatus::ACTIVE,
            'billing_interval' => SubscriptionInterval::MONTHLY,
            'price' => 49.99,
            'currency' => 'NGN',
            'gateway' => PaymentGateway::STRIPE,
            'starts_at' => $start,
            'current_period_start' => $start,
            'current_period_end' => $start->copy()->addMonth(),
        ];
    }

    public function trialing(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::TRIALING,
            'trial_ends_at' => now()->addDays(14),
        ]);
    }
}
