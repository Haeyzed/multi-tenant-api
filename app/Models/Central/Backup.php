<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\BackupStatus;
use App\Enums\Central\BackupType;
use App\Models\User;
use Database\Factories\Central\BackupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Platform or tenant database backup artifact.
 *
 * @property int $id
 * @property string $name
 * @property BackupType $type
 * @property BackupStatus $status
 * @property string $disk
 * @property string|null $path
 * @property int|null $size_bytes
 * @property bool $is_automatic
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $restored_at
 * @property string|null $error
 * @property int|null $created_by
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 *
 * @method static Builder<static> query()
 */
class Backup extends Model
{
    /** @use HasFactory<BackupFactory> */
    use CentralConnection, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
        'status',
        'disk',
        'path',
        'size_bytes',
        'is_automatic',
        'started_at',
        'completed_at',
        'restored_at',
        'error',
        'created_by',
        'metadata',
    ];

    protected static function newFactory(): BackupFactory
    {
        return BackupFactory::new();
    }

    /**
     * Platform user who initiated the backup, if manual.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BackupType::class,
            'status' => BackupStatus::class,
            'size_bytes' => 'integer',
            'is_automatic' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'restored_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
