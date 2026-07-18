<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\ImpersonationReason;
use App\Enums\Central\ImpersonationStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TenantImpersonation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TenantImpersonation>
 */
class TenantImpersonationFactory extends Factory
{
    protected $model = TenantImpersonation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'token' => Str::random(64),
            'reason' => ImpersonationReason::SUPPORT,
            'reason_notes' => fake()->optional()->sentence(),
            'status' => ImpersonationStatus::ACTIVE,
            'expires_at' => now()->addHour(),
        ];
    }
}
