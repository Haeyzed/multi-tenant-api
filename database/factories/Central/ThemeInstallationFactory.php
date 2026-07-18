<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Central\Theme;
use App\Models\Central\ThemeInstallation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ThemeInstallation> */
class ThemeInstallationFactory extends Factory
{
    protected $model = ThemeInstallation::class;

    public function definition(): array
    {
        return [
            'theme_id' => Theme::factory(),
            'is_active' => false,
            'installed_version' => '1.0.0',
        ];
    }
}
