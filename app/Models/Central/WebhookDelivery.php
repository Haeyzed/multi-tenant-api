<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\WebhookEvent;
use App\Enums\Central\WebhookStatus;
use Database\Factories\Central\WebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Individual delivery attempt for an outbound webhook.
 *
 * @property int $id
 * @property int $webhook_id
 * @property WebhookEvent $event
 * @property WebhookStatus $status
 * @property int $attempt
 * @property int|null $response_code
 * @property string|null $payload
 * @property string|null $response_body
 * @property string|null $error
 * @property Carbon|null $delivered_at
 * @property Carbon|null $next_retry_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Webhook $webhook
 *
 * @method static Builder<static> query()
 */
class WebhookDelivery extends Model
{
    /** @use HasFactory<WebhookDeliveryFactory> */
    use CentralConnection, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'webhook_id',
        'event',
        'status',
        'attempt',
        'response_code',
        'payload',
        'response_body',
        'error',
        'delivered_at',
        'next_retry_at',
    ];

    protected static function newFactory(): WebhookDeliveryFactory
    {
        return WebhookDeliveryFactory::new();
    }

    /**
     * Webhook endpoint that received this delivery.
     *
     * @return BelongsTo<Webhook, $this>
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => WebhookEvent::class,
            'status' => WebhookStatus::class,
            'attempt' => 'integer',
            'response_code' => 'integer',
            'delivered_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }
}
