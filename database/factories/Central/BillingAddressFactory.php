<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Central\BillingAddress;
use App\Models\Central\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BillingAddress> */
class BillingAddressFactory extends Factory
{
    protected $model = BillingAddress::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'company' => fake()->optional()->company(),
            'line1' => fake()->streetAddress(),
            'line2' => null,
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'postal_code' => fake()->postcode(),
            'country' => 'US',
            'tax_id' => null,
            'tax_type' => null,
            'is_default' => true,
        ];
    }
}
