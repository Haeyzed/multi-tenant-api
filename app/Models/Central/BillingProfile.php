<?php

declare(strict_types=1);

namespace App\Models\Central;

use Database\Factories\Central\BillingProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Billing preferences that control currency and gateway selection for a tenant.
 */
class BillingProfile extends Model
{
    /** @use HasFactory<BillingProfileFactory> */
    use CentralConnection;

    use HasFactory;

    protected $fillable = ['tenant_id', 'country_iso2', 'currency', 'preferred_gateway', 'metadata'];

    protected static function newFactory(): BillingProfileFactory
    {
        return BillingProfileFactory::new();
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
