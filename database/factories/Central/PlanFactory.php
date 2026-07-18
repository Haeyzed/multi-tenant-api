<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\PlanStatus;
use App\Enums\Central\PlanVisibility;
use App\Enums\Central\SubscriptionInterval;
use App\Models\Central\Plan;
use App\Models\Central\PlanPrice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 0, 299),
            'currency' => 'NGN',
            'billing_interval' => SubscriptionInterval::MONTHLY,
            'trial_days' => 14,
            'status' => PlanStatus::Active,
            'visibility' => PlanVisibility::Public,
            'is_featured' => false,
            'sort_order' => 0,
            'metadata' => [],
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Plan $plan): void {
            if ($plan->prices()->exists()) {
                return;
            }

            PlanPrice::query()->create([
                'plan_id' => $plan->id,
                'amount' => $plan->price,
                'currency' => $plan->currency,
                'billing_interval' => $plan->billing_interval,
                'trial_days' => $plan->trial_days,
                'status' => PlanStatus::Active,
            ]);
        });
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => PlanStatus::Draft,
            'visibility' => PlanVisibility::Private,
        ]);
    }
}
