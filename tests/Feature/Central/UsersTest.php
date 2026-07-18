<?php

declare(strict_types=1);

use App\Enums\Central\UserStatus;
use App\Models\User;
use App\Policies\Central\UserPolicy;
use Database\Seeders\Central\RbacSeeder;

it('lists users for authorized admins', function (): void {
    actingAsCentralUser(['users.view']);

    User::factory()->count(2)->create();

    $this->getJson('/api/v1/users')
        ->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonStructure(['meta' => ['total']]);
});

it('returns user overview statistics', function (): void {
    actingAsCentralUser(['users.view']);

    User::factory()->count(2)->create(['status' => UserStatus::Active]);
    User::factory()->create(['status' => UserStatus::Suspended]);

    $this->getJson('/api/v1/users/statistics')
        ->assertSuccessful()
        ->assertJsonPath('data.suspended', 1)
        ->assertJsonStructure([
            'data' => [
                'total',
                'active',
                'inactive',
                'suspended',
                'with_two_factor',
                'trashed',
                'by_status',
            ],
        ]);
});

it('creates a user', function (): void {
    actingAsCentralUser(['users.create', 'users.view']);

    $this->postJson('/api/v1/users', [
        'name' => 'New Operator',
        'email' => 'operator@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'roles' => ['operator'],
    ])->assertCreated()
        ->assertJsonPath('data.email', 'operator@example.com');
});

it('updates user status', function (): void {
    $admin = actingAsCentralUser(['users.manage-status', 'users.view']);
    $user = User::factory()->create();

    $this->putJson("/api/v1/users/{$user->id}/status", [
        'status' => UserStatus::Suspended->value,
    ])->assertSuccessful()
        ->assertJsonPath('data.status', 'suspended');

    expect($user->fresh()->status)->toBe(UserStatus::Suspended);
    expect($admin->id)->not->toBe($user->id);
});

it('soft deletes and restores a user', function (): void {
    actingAsCentralUser(['users.delete', 'users.restore', 'users.view']);
    $user = User::factory()->create();

    $this->deleteJson("/api/v1/users/{$user->id}")
        ->assertSuccessful();

    expect($user->fresh()->trashed())->toBeTrue();

    $this->postJson("/api/v1/users/{$user->id}/restore")
        ->assertSuccessful()
        ->assertJsonPath('data.email', $user->email);
});

it('syncs roles on a user', function (): void {
    actingAsCentralUser(['users.assign-roles', 'users.view']);
    $user = User::factory()->create();

    $this->putJson("/api/v1/users/{$user->id}/roles", [
        'roles' => ['operator'],
    ])->assertSuccessful()
        ->assertJsonPath('data.roles.0', 'operator');
});

it('returns a security summary', function (): void {
    actingAsCentralUser(['users.view']);
    $user = User::factory()->create();

    $this->getJson("/api/v1/users/{$user->id}/security")
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'two_factor_enabled',
                'last_login_at',
                'token_count',
                'email_verified',
            ],
        ]);
});

it('bulk deletes suspends and activates users', function (): void {
    $admin = actingAsCentralUser([
        'users.delete',
        'users.manage-status',
        'users.view',
    ]);

    $toDelete = User::factory()->count(2)->create();
    $toSuspend = User::factory()->count(2)->create(['status' => UserStatus::Active]);
    $toActivate = User::factory()->count(2)->create(['status' => UserStatus::Suspended]);

    $this->deleteJson('/api/v1/users/bulk', [
        'ids' => $toDelete->pluck('id')->all(),
    ])->assertSuccessful()
        ->assertJsonPath('data.deleted', 2);

    expect(User::onlyTrashed()->whereIn('id', $toDelete->pluck('id'))->count())->toBe(2);

    $this->postJson('/api/v1/users/bulk/suspend', [
        'ids' => $toSuspend->pluck('id')->all(),
    ])->assertSuccessful()
        ->assertJsonPath('data.suspended', 2);

    expect(
        User::query()
            ->whereIn('id', $toSuspend->pluck('id'))
            ->where('status', UserStatus::Suspended)
            ->count()
    )->toBe(2);

    $this->postJson('/api/v1/users/bulk/activate', [
        'ids' => $toActivate->pluck('id')->all(),
    ])->assertSuccessful()
        ->assertJsonPath('data.activated', 2);

    expect(
        User::query()
            ->whereIn('id', $toActivate->pluck('id'))
            ->where('status', UserStatus::Active)
            ->count()
    )->toBe(2);

    $this->deleteJson('/api/v1/users/bulk', [
        'ids' => [$admin->id],
    ])->assertUnprocessable();
});

it('forbids bulk user actions without permission', function (): void {
    actingAsCentralUser(['users.view']);
    $users = User::factory()->count(2)->create();

    $this->deleteJson('/api/v1/users/bulk', [
        'ids' => $users->pluck('id')->all(),
    ])->assertForbidden();

    $this->postJson('/api/v1/users/bulk/suspend', [
        'ids' => $users->pluck('id')->all(),
    ])->assertForbidden();

    $this->postJson('/api/v1/users/bulk/activate', [
        'ids' => $users->pluck('id')->all(),
    ])->assertForbidden();
});

it('forbids viewing users without permission', function (): void {
    test()->seed(RbacSeeder::class);
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/users')
        ->assertForbidden();
});

it('authorizes user policy actions', function (): void {
    test()->seed(RbacSeeder::class);

    $admin = User::factory()->create();
    $admin->givePermissionTo(['users.view', 'users.delete', 'users.update']);

    $target = User::factory()->create();
    $policy = new UserPolicy;

    expect($policy->view($admin, $target))->toBeTrue()
        ->and($policy->delete($admin, $target))->toBeTrue()
        ->and($policy->delete($admin, $admin))->toBeFalse();
});
