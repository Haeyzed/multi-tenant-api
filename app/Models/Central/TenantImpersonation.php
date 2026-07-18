<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\ImpersonationReason;
use App\Enums\Central\ImpersonationStatus;
use App\Models\User;
use Database\Factories\Central\TenantImpersonationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Audited impersonation session for accessing a tenant context.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $user_id
 * @property string $token
 * @property ImpersonationReason $reason
 * @property string|null $reason_notes
 * @property ImpersonationStatus $status
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $expires_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read User $user
 *
 * @method static Builder<static> query()
 */
class TenantImpersonation extends Model
{
    /** @use HasFactory<TenantImpersonationFactory> */
    use CentralConnection;

    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'token',
        'reason',
        'reason_notes',
        'status',
        'ip_address',
        'user_agent',
        'expires_at',
        'ended_at',
    ];

    protected static function newFactory(): TenantImpersonationFactory
    {
        return TenantImpersonationFactory::new();
    }

    /**
     * Tenant being impersonated.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Platform user performing the impersonation.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Determine whether the impersonation session is still active.
     */
    public function isActive(): bool
    {
        return $this->status === ImpersonationStatus::ACTIVE
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => ImpersonationReason::class,
            'status' => ImpersonationStatus::class,
            'expires_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
