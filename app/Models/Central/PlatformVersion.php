<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\PlatformVersionStatus;
use Database\Factories\Central\PlatformVersionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Deployed platform version with release and migration metadata.
 *
 * @property int $id
 * @property string $version
 * @property PlatformVersionStatus $status
 * @property string|null $release_notes
 * @property bool $is_current
 * @property Carbon|null $released_at
 * @property Carbon|null $rolled_back_at
 * @property array<string, mixed>|null $migration_status
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 */
class PlatformVersion extends Model
{
    /** @use HasFactory<PlatformVersionFactory> */
    use CentralConnection, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'version',
        'status',
        'release_notes',
        'is_current',
        'released_at',
        'rolled_back_at',
        'migration_status',
        'metadata',
    ];

    protected static function newFactory(): PlatformVersionFactory
    {
        return PlatformVersionFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PlatformVersionStatus::class,
            'is_current' => 'boolean',
            'released_at' => 'datetime',
            'rolled_back_at' => 'datetime',
            'migration_status' => 'array',
            'metadata' => 'array',
        ];
    }
}
