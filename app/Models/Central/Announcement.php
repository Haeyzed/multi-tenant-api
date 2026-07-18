<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\AnnouncementStatus;
use App\Enums\Central\AnnouncementTarget;
use App\Enums\Central\AnnouncementType;
use App\Models\User;
use Database\Factories\Central\AnnouncementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Platform-wide or targeted announcement shown to tenants.
 *
 * @property int $id
 * @property string $title
 * @property string $body
 * @property AnnouncementType $type
 * @property AnnouncementTarget $target
 * @property AnnouncementStatus $status
 * @property bool $is_dismissible
 * @property list<int>|null $target_plan_ids
 * @property list<string>|null $target_tenant_ids
 * @property list<string>|null $regions
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $published_at
 * @property int|null $created_by
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $creator
 * @property-read Collection<int, AnnouncementHistory> $histories
 *
 * @method static Builder<static> query()
 */
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use CentralConnection, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'body',
        'type',
        'target',
        'status',
        'is_dismissible',
        'target_plan_ids',
        'target_tenant_ids',
        'regions',
        'starts_at',
        'ends_at',
        'published_at',
        'created_by',
        'metadata',
    ];

    protected static function newFactory(): AnnouncementFactory
    {
        return AnnouncementFactory::new();
    }

    /**
     * Platform user who created the announcement.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Audit history for announcement changes.
     *
     * @return HasMany<AnnouncementHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(AnnouncementHistory::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AnnouncementType::class,
            'target' => AnnouncementTarget::class,
            'status' => AnnouncementStatus::class,
            'is_dismissible' => 'boolean',
            'target_plan_ids' => 'array',
            'target_tenant_ids' => 'array',
            'regions' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
