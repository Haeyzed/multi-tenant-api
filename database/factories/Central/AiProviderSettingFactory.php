<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\AIProvider;
use App\Models\Central\AiProviderSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiProviderSetting> */
class AiProviderSettingFactory extends Factory
{
    protected $model = AiProviderSetting::class;

    public function definition(): array
    {
        $provider = fake()->randomElement(AIProvider::cases());

        return [
            'provider' => $provider,
            'label' => $provider->label(),
            'is_enabled' => false,
            'default_model' => $provider->defaultModel(),
            'monthly_token_limit' => 1_000_000,
            'monthly_token_usage' => 0,
            'credits_remaining' => 100,
            'config' => [],
        ];
    }
}
