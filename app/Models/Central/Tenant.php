<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\TenantStatus;
use Database\Factories\Central\TenantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Central tenant record with isolated database and domain routing.
 *
 * Represents a customer organization on the platform, including lifecycle
 * status, trial windows, and suspension metadata.
 *
 * @property string $id
 * @property string|null $name
 * @property string|null $slug
 * @property string|null $email
 * @property string|null $phone
 * @property TenantStatus $status
 * @property list<string>|null $tags
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $suspended_at
 * @property string|null $suspended_reason
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, BillingAddress> $billingAddresses
 * @property-read Collection<int, Domain> $domains
 * @property-read Collection<int, Invoice> $invoices
 * @property-read Collection<int, TenantImpersonation> $impersonations
 * @property-read Collection<int, TenantNote> $notes
 * @property-read Collection<int, Subscription> $subscriptions
 *
 * @method static Builder<static> query()
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    /** @use HasFactory<TenantFactory> */
    use HasDatabase;

    use HasDomains;
    use HasFactory;
    use SoftDeletes;

    /**
     * Return tenant columns stored as real database columns rather than JSON data.
     *
     * @return list<string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'email',
            'phone',
            'status',
            'tags',
            'metadata',
            'trial_ends_at',
            'suspended_at',
            'suspended_reason',
            'archived_at',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }

    /**
     * Internal notes attached to this tenant.
     *
     * @return HasMany<TenantNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(TenantNote::class);
    }

    /**
     * Impersonation sessions initiated for this tenant.
     *
     * @return HasMany<TenantImpersonation, $this>
     */
    public function impersonations(): HasMany
    {
        return $this->hasMany(TenantImpersonation::class);
    }

    /**
     * Billing addresses associated with this tenant.
     *
     * @return HasMany<BillingAddress, $this>
     */
    public function billingAddresses(): HasMany
    {
        return $this->hasMany(BillingAddress::class);
    }

    /**
     * Subscriptions owned by this tenant.
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Invoices issued to this tenant.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Determine whether the tenant may access the platform.
     */
    public function canAccessPlatform(): bool
    {
        return ($this->status ?? TenantStatus::PENDING)->canAccess();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'tags' => 'array',
            'metadata' => 'array',
            'trial_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
            'archived_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
