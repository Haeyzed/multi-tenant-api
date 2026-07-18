<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\IntegrationStatus;
use App\Models\Central\InstalledIntegration;
use App\Models\Central\Integration;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InstalledIntegration> */
class InstalledIntegrationFactory extends Factory
{
    protected $model = InstalledIntegration::class;

    public function definition(): array
    {
        return [
            'integration_id' => Integration::factory(),
            'status' => IntegrationStatus::PENDING,
            'installed_version' => '1.0.0',
            'configuration' => [],
        ];
    }
}
