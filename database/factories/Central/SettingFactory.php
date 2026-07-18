<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\SettingGroup;
use App\Enums\Central\SettingType;
use App\Models\Central\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = 'setting_'.Str::lower(Str::random(8));

        return [
            'group' => SettingGroup::Platform,
            'key' => $key,
            'label' => Str::title(str_replace('_', ' ', $key)),
            'description' => fake()->sentence(),
            'type' => SettingType::STRING,
            'value' => fake()->word(),
            'default_value' => ['value' => null],
            'options' => null,
            'is_public' => false,
            'is_encrypted' => false,
            'is_readonly' => false,
            'sort_order' => 0,
        ];
    }

    public function encrypted(): static
    {
        return $this->state(fn (): array => [
            'type' => SettingType::ENCRYPTED,
            'is_encrypted' => true,
            'value' => encrypt('secret-value'),
        ]);
    }
}
