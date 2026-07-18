<?php

declare(strict_types=1);

use App\Enums\Central\FeatureStatus;
use App\Enums\Central\PlanFeatureLimitType;
use App\Models\Central\Feature;
use App\Models\Central\FeatureCategory;

it('manages feature categories and features', function (): void {
    actingAsCentralUser([
        'features.view',
        'features.create',
        'features.update',
        'features.delete',
        'features.restore',
        'features.manage-categories',
    ]);

    $category = $this->postJson('/api/v1/feature-categories', [
        'name' => 'Commerce',
        'icon' => 'cart',
    ])->assertCreated()
        ->assertJsonPath('data.slug', 'commerce');

    $categoryId = $category->json('data.id');

    $feature = $this->postJson('/api/v1/features', [
        'feature_category_id' => $categoryId,
        'name' => 'Product Limit',
        'key' => 'products_limit',
        'default_limit_type' => PlanFeatureLimitType::COUNT->value,
        'default_limit_value' => 100,
        'tracks_usage' => true,
        'unit' => 'products',
    ])->assertCreated()
        ->assertJsonPath('data.key', 'products_limit')
        ->assertJsonPath('data.status', FeatureStatus::Active->value);

    $featureId = $feature->json('data.id');

    $this->getJson('/api/v1/features')
        ->assertSuccessful()
        ->assertJsonPath('status', true);

    $this->putJson("/api/v1/features/{$featureId}", [
        'description' => 'Max sellable products',
    ])->assertSuccessful()
        ->assertJsonPath('data.description', 'Max sellable products');

    $this->deleteJson("/api/v1/features/{$featureId}")
        ->assertSuccessful();

    expect(Feature::withTrashed()->find($featureId)?->trashed())->toBeTrue();

    $this->postJson("/api/v1/features/{$featureId}/restore")
        ->assertSuccessful();

    $this->getJson('/api/v1/feature-categories')
        ->assertSuccessful();

    $this->getJson('/api/v1/feature-categories/options')
        ->assertSuccessful()
        ->assertJsonPath('data.0.value', $categoryId)
        ->assertJsonPath('data.0.label', 'Commerce');

    expect(FeatureCategory::query()->find($categoryId))->not->toBeNull();
});

it('forbids feature listing without permission', function (): void {
    $user = \App\Models\User::factory()->create();
    test()->seed(\Database\Seeders\Central\RbacSeeder::class);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/features')
        ->assertForbidden();
});
