<?php

declare(strict_types=1);

use App\Enums\Central\PlanFeatureLimitType;
use App\Models\Central\Feature;
use App\Models\Central\Plan;
use App\Services\Central\Billing\PlanService;

it('syncs boolean unlimited and count features onto a plan', function (): void {
    $plan = Plan::factory()->create();
    $count = Feature::factory()->countable()->create();
    $bool = Feature::factory()->create(['default_limit_type' => PlanFeatureLimitType::BOOLEAN]);
    $unlimited = Feature::factory()->create(['default_limit_type' => PlanFeatureLimitType::UNLIMITED]);

    $service = app(PlanService::class);
    $updated = $service->syncFeatures($plan, [
        [
            'feature_id' => $count->id,
            'limit_type' => PlanFeatureLimitType::COUNT->value,
            'limit_value' => 25,
            'tracks_usage' => true,
        ],
        [
            'feature_id' => $bool->id,
            'limit_type' => PlanFeatureLimitType::BOOLEAN->value,
            'is_enabled' => true,
        ],
        [
            'feature_id' => $unlimited->id,
            'limit_type' => PlanFeatureLimitType::UNLIMITED->value,
            'is_unlimited' => true,
        ],
    ]);

    expect($updated->features)->toHaveCount(3)
        ->and($updated->features->firstWhere('id', $unlimited->id)?->pivot->is_unlimited)->toBeTruthy();
});
