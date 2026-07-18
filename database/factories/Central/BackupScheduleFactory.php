<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\BackupType;
use App\Models\Central\BackupSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BackupSchedule> */
class BackupScheduleFactory extends Factory
{
    protected $model = BackupSchedule::class;

    public function definition(): array
    {
        return [
            'name' => 'Nightly full backup',
            'type' => BackupType::FULL,
            'cron_expression' => '0 2 * * *',
            'retention_days' => 30,
            'is_active' => true,
            'metadata' => [],
        ];
    }
}
