<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\NotificationStatus;
use App\Models\User;
use Database\Factories\Central\PlatformNotificationFactory;
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
 * Platform notification broadcast to selected users or channels.
 *
 * @property int $id
 * @property string $title
 * @property string $body
 * @property list<string> $channels
 * @property NotificationStatus $status
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $sent_at
 * @property int|null $created_by
 * @property list<int>|null $target_user_ids
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $creator
 * @property-read Collection<int, NotificationDelivery> $deliveries
 *
 * @method static Builder<static> query()
 */
class PlatformNotification extends Model
{
    /** @use HasFactory<PlatformNotificationFactory> */
    use CentralConnection, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'body',
        'channels',
        'status',
        'scheduled_at',
        'sent_at',
        'created_by',
        'target_user_ids',
        'metadata',
    ];

    protected static function newFactory(): PlatformNotificationFactory
    {
        return PlatformNotificationFactory::new();
    }

    /**
     * Platform user who created the notification.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Per-user delivery records for this notification.
     *
     * @return HasMany<NotificationDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'status' => NotificationStatus::class,
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'target_user_ids' => 'array',
            'metadata' => 'array',
        ];
    }
}
