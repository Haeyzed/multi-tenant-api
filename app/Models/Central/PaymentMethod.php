<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\PaymentGateway;
use App\Enums\Central\PaymentMethodStatus;
use Database\Factories\Central\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Stored payment method for a tenant (tokenized card / authorization).
 *
 * @property int $id
 * @property string $tenant_id
 * @property PaymentGateway $gateway
 * @property PaymentMethodStatus $status
 * @property string|null $external_id
 * @property string|null $customer_external_id
 * @property string|null $authorization_code
 * @property string|null $brand
 * @property string|null $last_four
 * @property int|null $exp_month
 * @property int|null $exp_year
 * @property bool $is_default
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use CentralConnection, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'gateway',
        'status',
        'external_id',
        'customer_external_id',
        'authorization_code',
        'brand',
        'last_four',
        'exp_month',
        'exp_year',
        'is_default',
        'meta',
    ];

    protected static function newFactory(): PaymentMethodFactory
    {
        return PaymentMethodFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gateway' => PaymentGateway::class,
            'status' => PaymentMethodStatus::class,
            'is_default' => 'boolean',
            'exp_month' => 'integer',
            'exp_year' => 'integer',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'default_payment_method_id');
    }
}
