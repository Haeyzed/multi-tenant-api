<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\BackupStatus;
use App\Enums\Central\BackupType;
use App\Models\Central\Backup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Backup> */
class BackupFactory extends Factory
{
    protected $model = Backup::class;

    public function definition(): array
    {
        return [
            'name' => 'backup-'.now()->format('Ymd-His'),
            'type' => BackupType::FULL,
            'status' => BackupStatus::PENDING,
            'disk' => 'local',
            'is_automatic' => false,
            'metadata' => [],
        ];
    }
}
