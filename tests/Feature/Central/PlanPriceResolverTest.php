<?php

declare(strict_types=1);

use App\Enums\Central\PlanStatus;
use App\Enums\Central\SettingGroup;
use App\Enums\Central\SettingType;
use App\Enums\Central\SubscriptionInterval;
use App\Models\Central\Plan;
use App\Models\Central\PlanPrice;
use App\Models\Central\Setting;
use App\Services\Central\Billing\PlanPriceResolver;
use App\Services\Central\Settings\SettingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Cache::forget('central.settings.map');

    Setting::query()->updateOrCreate(
        ['key' => 'billing.default_currency'],
        [
            'group' => SettingGroup::Billing,
            'label' => 'Default Billing Currency',
            'type' => SettingType::STRING,
            'value' => 'USD',
            'default_value' => ['value' => 'USD'],
            'sort_order' => 3,
        ],
    );

    app(SettingService::class)->forgetCache();

    seedWorldCountry('NG', 'Nigeria', 'NGN');
    seedWorldCountry('US', 'United States', 'USD');
});

it('resolves NGN price for Nigeria and USD for United States', function (): void {
    $plan = Plan::factory()->create([
        'price' => 29,
        'currency' => 'USD',
        'trial_days' => 1,
    ]);

    PlanPrice::query()->where('plan_id', $plan->id)->delete();

    PlanPrice::factory()->create([
        'plan_id' => $plan->id,
        'amount' => 15000,
        'currency' => 'NGN',
        'billing_interval' => SubscriptionInterval::MONTHLY,
        'trial_days' => 1,
        'status' => PlanStatus::Active,
    ]);

    PlanPrice::factory()->create([
        'plan_id' => $plan->id,
        'amount' => 29,
        'currency' => 'USD',
        'billing_interval' => SubscriptionInterval::MONTHLY,
        'trial_days' => 1,
        'status' => PlanStatus::Active,
    ]);

    $resolver = app(PlanPriceResolver::class);

    $ngPrice = $resolver->resolve($plan->fresh('prices'), 'NG');
    $usPrice = $resolver->resolve($plan->fresh('prices'), 'US');

    expect($ngPrice->currency)->toBe('NGN')
        ->and((float) $ngPrice->amount)->toBe(15000.0)
        ->and($usPrice->currency)->toBe('USD')
        ->and((float) $usPrice->amount)->toBe(29.0);
});

it('rejects inactive plans and honors exact price fallback policy', function (): void {
    $inactivePlan = Plan::factory()->create(['status' => PlanStatus::Archived]);

    expect(fn () => app(PlanPriceResolver::class)->resolve($inactivePlan))
        ->toThrow(ValidationException::class);

    Setting::query()->updateOrCreate(
        ['key' => 'billing.price_fallback_mode'],
        [
            'group' => SettingGroup::Billing,
            'label' => 'Price Fallback Mode',
            'type' => SettingType::SELECT,
            'value' => 'exact_currency',
            'default_value' => ['value' => 'any_active'],
            'options' => ['exact_currency', 'any_active'],
        ],
    );
    app(SettingService::class)->forgetCache();

    $plan = Plan::factory()->create(['status' => PlanStatus::Active]);
    PlanPrice::query()->where('plan_id', $plan->id)->delete();
    PlanPrice::factory()->create([
        'plan_id' => $plan->id,
        'currency' => 'USD',
        'billing_interval' => SubscriptionInterval::MONTHLY,
        'status' => PlanStatus::Active,
    ]);

    expect(fn () => app(PlanPriceResolver::class)->resolve(
        $plan,
        'NG',
        null,
        SubscriptionInterval::MONTHLY,
    ))->toThrow(ValidationException::class);
});
