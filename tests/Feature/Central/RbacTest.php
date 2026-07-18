<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Central\PermissionCatalog;
use Database\Seeders\Central\RbacSeeder;
use App\Models\Central\Role;

it('lists roles for authorized users', function (): void {
    actingAsCentralUser(['roles.view']);

    $this->getJson('/api/v1/roles')
        ->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonStructure(['data', 'meta' => ['current_page', 'total']]);
});

it('forbids role listing without permission', function (): void {
    $user = User::factory()->create();
    test()->seed(RbacSeeder::class);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/roles')
        ->assertForbidden();
});

it('creates a role with permissions', function (): void {
    actingAsCentralUser(['roles.create', 'roles.view']);

    $this->postJson('/api/v1/roles', [
        'name' => 'auditor',
        'permissions' => ['users.view', 'permissions.view'],
    ])->assertCreated()
        ->assertJsonPath('data.name', 'auditor');

    expect(Role::findByName('auditor', PermissionCatalog::GUARD)->permissions)->toHaveCount(2);
});

it('updates and deletes a role', function (): void {
    actingAsCentralUser(['roles.create', 'roles.update', 'roles.delete', 'roles.view']);

    $role = Role::create(['name' => 'temp-role', 'guard_name' => PermissionCatalog::GUARD]);

    $this->putJson("/api/v1/roles/{$role->id}", [
        'name' => 'renamed-role',
    ])->assertSuccessful()
        ->assertJsonPath('data.name', 'renamed-role');

    $this->deleteJson("/api/v1/roles/{$role->id}")
        ->assertSuccessful();

    expect(Role::query()->where('name', 'renamed-role')->exists())->toBeFalse();
});

it('prevents deleting the super-admin role', function (): void {
    actingAsCentralUser(['roles.delete']);

    $role = Role::findByName('super-admin', PermissionCatalog::GUARD);

    $this->deleteJson("/api/v1/roles/{$role->id}")
        ->assertStatus(422);
});

it('lists grouped permissions', function (): void {
    actingAsCentralUser(['permissions.view']);

    $this->getJson('/api/v1/permissions/grouped')
        ->assertSuccessful()
        ->assertJsonPath('status', true);
});

it('paginates permissions and supports CRUD with bulk delete', function (): void {
    actingAsCentralUser([
        'permissions.view',
        'permissions.create',
        'permissions.update',
        'permissions.delete',
    ]);

    $this->getJson('/api/v1/permissions')
        ->assertSuccessful()
        ->assertJsonStructure(['data', 'meta' => ['total']]);

    $created = $this->postJson('/api/v1/permissions', [
        'name' => 'reports.export',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'reports.export')
        ->json('data');

    $this->putJson("/api/v1/permissions/{$created['id']}", [
        'name' => 'reports.download',
    ])->assertSuccessful()
        ->assertJsonPath('data.name', 'reports.download');

    $second = $this->postJson('/api/v1/permissions', [
        'name' => 'reports.print',
    ])->assertCreated()
        ->json('data');

    $this->deleteJson('/api/v1/permissions/bulk', [
        'ids' => [$created['id'], $second['id']],
    ])->assertSuccessful()
        ->assertJsonPath('data.deleted', 2);
});

it('returns a permission matrix', function (): void {
    actingAsCentralUser(['permissions.view', 'roles.view']);

    $this->getJson('/api/v1/permissions/matrix')
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'groups',
                'roles',
                'matrix',
            ],
        ]);
});

it('returns role statistics', function (): void {
    actingAsCentralUser(['roles.view']);

    $this->getJson('/api/v1/roles/statistics')
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'total_roles',
                'total_permissions',
                'assigned_users',
                'groups',
            ],
        ]);
});

it('bulk deletes roles except super-admin', function (): void {
    actingAsCentralUser(['roles.create', 'roles.delete', 'roles.view']);

    $roleA = Role::create(['name' => 'temp-a', 'guard_name' => PermissionCatalog::GUARD]);
    $roleB = Role::create(['name' => 'temp-b', 'guard_name' => PermissionCatalog::GUARD]);
    $superAdmin = Role::findByName('super-admin', PermissionCatalog::GUARD);

    $this->deleteJson('/api/v1/roles/bulk', [
        'ids' => [$roleA->id, $roleB->id, $superAdmin->id],
    ])->assertSuccessful()
        ->assertJsonPath('data.deleted', 2);

    expect(Role::query()->whereIn('id', [$roleA->id, $roleB->id])->count())->toBe(0)
        ->and(Role::query()->whereKey($superAdmin->id)->exists())->toBeTrue();
});
