<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\UserStatus;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'is_owner' => false,
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (): array => [
            'is_owner' => true,
            'name' => 'Store Owner',
        ]);
    }

    public function invited(): static
    {
        return $this->state(fn (): array => [
            'password' => null,
            'status' => UserStatus::Invited,
            'invitation_token' => hash('sha256', Str::random(64)),
            'invitation_expires_at' => now()->addDays(3),
            'email_verified_at' => null,
            'is_owner' => true,
        ]);
    }
}
