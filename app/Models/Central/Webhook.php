<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Models\User;
use Database\Factories\Central\WebhookFactory;
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
 * Outbound webhook endpoint subscribed to platform events.
 *
 * @property int $id
 * @property string $name
 * @property string $url
 * @property string $secret
 * @property list<string> $events
 * @property bool $is_active
 * @property int $max_retries
 * @property int $timeout_seconds
 * @property int|null $api_client_id
 * @property int|null $created_by
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read ApiClient|null $client
 * @property-read User|null $creator
 * @property-read Collection<int, WebhookDelivery> $deliveries
 *
 * @method static Builder<static> query()
 */
class Webhook extends Model
{
    /** @use HasFactory<WebhookFactory> */
    use CentralConnection, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'url',
        'secret',
        'events',
        'is_active',
        'max_retries',
        'timeout_seconds',
        'api_client_id',
        'created_by',
        'metadata',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'secret',
    ];

    protected static function newFactory(): WebhookFactory
    {
        return WebhookFactory::new();
    }

    /**
     * API client that owns this webhook, if any.
     *
     * @return BelongsTo<ApiClient, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id');
    }

    /**
     * Platform user who created this webhook.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Delivery attempts for this webhook.
     *
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'max_retries' => 'integer',
            'timeout_seconds' => 'integer',
            'secret' => 'encrypted',
            'metadata' => 'array',
        ];
    }
}
