<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\PaymentGateway;
use App\Enums\Central\SignupIntentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Short-lived public signup + card verification session.
 *
 * @property string $id
 * @property SignupIntentStatus $status
 * @property string $email
 * @property PaymentGateway $gateway
 * @property string $currency
 * @property string $verification_amount
 * @property string|null $gateway_reference
 * @property string|null $checkout_url
 * @property array<string, mixed> $payload
 * @property string|null $password_secret
 * @property array<string, mixed>|null $verification_meta
 * @property string|null $tenant_id
 * @property Carbon $expires_at
 * @property Carbon|null $verified_at
 * @property Carbon|null $completed_at
 */
class SignupIntent extends Model
{
    use CentralConnection, HasUuids, MassPrunable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'status',
        'email',
        'gateway',
        'currency',
        'verification_amount',
        'gateway_reference',
        'checkout_url',
        'payload',
        'password_secret',
        'verification_meta',
        'tenant_id',
        'expires_at',
        'verified_at',
        'completed_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password_secret',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SignupIntentStatus::class,
            'gateway' => PaymentGateway::class,
            'verification_amount' => 'decimal:2',
            'payload' => 'array',
            'password_secret' => 'encrypted',
            'verification_meta' => 'array',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast()
            || $this->status === SignupIntentStatus::Expired;
    }

    /**
     * @return Builder<SignupIntent>
     */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<=', now()->subMinutes(15));
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
