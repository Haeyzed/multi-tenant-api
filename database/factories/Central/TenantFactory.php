<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\TenantStatus;
use App\Models\Central\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();
        $slug = Str::slug($name).'-'.Str::lower(Str::random(4));

        return [
            'id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => $slug,
            'email' => fake()->companyEmail(),
            'phone' => fake()->optional()->e164PhoneNumber(),
            'status' => TenantStatus::ACTIVE,
            'tags' => ['saas'],
            'metadata' => ['source' => 'factory'],
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => TenantStatus::PENDING]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => TenantStatus::SUSPENDED,
            'suspended_at' => now(),
            'suspended_reason' => 'Policy violation',
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => TenantStatus::ARCHIVED,
            'archived_at' => now(),
        ]);
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Tenant $tenant): void {
            //
        })->afterCreating(function (Tenant $tenant): void {
            // Database provisioning is skipped in tests via TenancyServiceProvider.
        });
    }
}
