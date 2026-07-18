<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Central\Tenant;
use App\Models\Central\TenantNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantNote>
 */
class TenantNoteFactory extends Factory
{
    protected $model = TenantNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
            'is_internal' => true,
        ];
    }
}
