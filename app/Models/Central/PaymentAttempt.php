<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Individual gateway attempt for a payment.
 *
 * @property int $id
 * @property int $payment_id
 * @property int $attempt_number
 * @property PaymentStatus $status
 * @property string|null $gateway_reference
 * @property string|null $response_message
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Payment $payment
 *
 * @method static Builder<static> query()
 */
class PaymentAttempt extends Model
{
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payment_id',
        'attempt_number',
        'status',
        'gateway_reference',
        'response_message',
        'payload',
    ];

    /**
     * Payment this attempt belongs to.
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
            'status' => PaymentStatus::class,
            'attempt_number' => 'integer',
            'payload' => 'array',
        ];
    }
}
