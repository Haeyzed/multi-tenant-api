<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\DeliveryStatus;
use App\Enums\Central\NotificationChannel;
use App\Models\User;
use Database\Factories\Central\NotificationDeliveryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Delivery attempt of a platform notification to a user and channel.
 *
 * @property int $id
 * @property int $platform_notification_id
 * @property int|null $user_id
 * @property NotificationChannel $channel
 * @property DeliveryStatus $status
 * @property Carbon|null $delivered_at
 * @property Carbon|null $read_at
 * @property string|null $error
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PlatformNotification $notification
 * @property-read User|null $user
 *
 * @method static Builder<static> query()
 */
class NotificationDelivery extends Model
{
    /** @use HasFactory<NotificationDeliveryFactory> */
    use CentralConnection, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'platform_notification_id',
        'user_id',
        'channel',
        'status',
        'delivered_at',
        'read_at',
        'error',
        'metadata',
    ];

    protected static function newFactory(): NotificationDeliveryFactory
    {
        return NotificationDeliveryFactory::new();
    }

    /**
     * Notification being delivered.
     *
     * @return BelongsTo<PlatformNotification, $this>
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(PlatformNotification::class, 'platform_notification_id');
    }

    /**
     * Recipient user for this delivery.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'status' => DeliveryStatus::class,
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
