<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Central\BillingProfile;
use App\Models\Central\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingProfile>
 */
class BillingProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'country_iso2' => fake()->countryCode(),
            'currency' => 'USD',
            'preferred_gateway' => 'stripe',
            'metadata' => [],
        ];
    }
}
