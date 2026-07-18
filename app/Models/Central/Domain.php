<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\DomainStatus;
use App\Enums\Central\DomainType;
use Database\Factories\Central\DomainFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

/**
 * Domain hostname mapped to a central tenant.
 *
 * Tracks DNS verification, SSL configuration, and redirect behavior
 * for tenant-facing URLs.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $domain
 * @property DomainType $type
 * @property DomainStatus $status
 * @property bool $is_primary
 * @property bool $is_redirect
 * @property string|null $redirect_to
 * @property string|null $dns_verification_token
 * @property Carbon|null $dns_verified_at
 * @property bool $ssl_enabled
 * @property string|null $ssl_status
 * @property Carbon|null $ssl_expires_at
 * @property bool $force_https
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 *
 * @method static Builder<static> query()
 */
class Domain extends BaseDomain
{
    /** @use HasFactory<DomainFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'domain',
        'type',
        'status',
        'is_primary',
        'is_redirect',
        'redirect_to',
        'dns_verification_token',
        'dns_verified_at',
        'ssl_enabled',
        'ssl_status',
        'ssl_expires_at',
        'force_https',
    ];

    protected static function newFactory(): DomainFactory
    {
        return DomainFactory::new();
    }

    /**
     * Tenant that owns this domain.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Determine whether DNS verification has completed.
     */
    public function isVerified(): bool
    {
        return filled($this->dns_verified_at);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DomainType::class,
            'status' => DomainStatus::class,
            'is_primary' => 'boolean',
            'is_redirect' => 'boolean',
            'dns_verified_at' => 'datetime',
            'ssl_enabled' => 'boolean',
            'ssl_expires_at' => 'datetime',
            'force_https' => 'boolean',
        ];
    }
}
