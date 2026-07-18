<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\DomainStatus;
use App\Enums\Central\DomainType;
use App\Models\Central\Domain;
use App\Models\Central\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    protected $model = Domain::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'domain' => fake()->unique()->domainName(),
            'type' => DomainType::CUSTOM,
            'status' => DomainStatus::PENDING,
            'is_primary' => false,
            'is_redirect' => false,
            'force_https' => true,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (): array => [
            'is_primary' => true,
            'type' => DomainType::PRIMARY,
            'status' => DomainStatus::ACTIVE,
        ]);
    }

    public function subdomain(): static
    {
        return $this->state(fn (): array => [
            'type' => DomainType::SUBDOMAIN,
            'status' => DomainStatus::ACTIVE,
            'domain' => fake()->unique()->slug(2).'.localhost',
        ]);
    }
}
