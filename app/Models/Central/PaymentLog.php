<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\LogLevel;
use App\Enums\Central\PaymentGateway;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Structured log entry for payment gateway activity.
 *
 * @property int $id
 * @property int|null $payment_id
 * @property string|null $tenant_id
 * @property PaymentGateway|null $gateway
 * @property string $event
 * @property LogLevel $level
 * @property string|null $message
 * @property array<string, mixed>|null $context
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Payment|null $payment
 *
 * @method static Builder<static> query()
 */
class PaymentLog extends Model
{
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payment_id',
        'tenant_id',
        'gateway',
        'event',
        'level',
        'message',
        'context',
    ];

    /**
     * Payment associated with this log entry, if any.
     *
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gateway' => PaymentGateway::class,
            'level' => LogLevel::class,
            'context' => 'array',
        ];
    }
}
