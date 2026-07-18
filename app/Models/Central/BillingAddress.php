<?php

declare(strict_types=1);

namespace App\Models\Central;

use Database\Factories\Central\BillingAddressFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Billing address stored for a tenant.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $company
 * @property string $line1
 * @property string|null $line2
 * @property string $city
 * @property string|null $state
 * @property string $postal_code
 * @property string $country
 * @property string|null $tax_id
 * @property string|null $tax_type
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read Collection<int, Invoice> $invoices
 *
 * @method static Builder<static> query()
 */
class BillingAddress extends Model
{
    /** @use HasFactory<BillingAddressFactory> */
    use CentralConnection, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'company',
        'line1',
        'line2',
        'city',
        'state',
        'postal_code',
        'country',
        'tax_id',
        'tax_type',
        'is_default',
    ];

    protected static function newFactory(): BillingAddressFactory
    {
        return BillingAddressFactory::new();
    }

    /**
     * Tenant that owns this billing address.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Invoices that reference this billing address.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }
}
