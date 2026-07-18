<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\BackupType;
use Database\Factories\Central\BackupScheduleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Scheduled backup job configuration.
 *
 * @property int $id
 * @property string $name
 * @property BackupType $type
 * @property string $cron_expression
 * @property int $retention_days
 * @property bool $is_active
 * @property Carbon|null $last_run_at
 * @property Carbon|null $next_run_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 */
class BackupSchedule extends Model
{
    /** @use HasFactory<BackupScheduleFactory> */
    use CentralConnection, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
        'cron_expression',
        'retention_days',
        'is_active',
        'last_run_at',
        'next_run_at',
        'metadata',
    ];

    protected static function newFactory(): BackupScheduleFactory
    {
        return BackupScheduleFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BackupType::class,
            'retention_days' => 'integer',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
