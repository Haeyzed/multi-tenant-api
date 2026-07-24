<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Models\World\Country;
use App\Models\World\Currency;
use Database\Factories\Central\PaymentGatewayFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Platform payment gateway catalog entry (database-driven provider definition).
 *
 * Distinct from {@see \App\Enums\Central\PaymentGateway}, which remains the
 * string slug enum used on payments and subscriptions.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $driver
 * @property int $priority
 * @property bool $is_active
 * @property bool $is_fallback
 * @property bool $supports_subscription
 * @property bool $supports_refund
 * @property bool $supports_webhook
 * @property bool $supports_partial_refund
 * @property array<string, mixed>|null $config
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Currency> $currencies
 * @property-read Collection<int, Country> $countries
 *
 * @method static Builder<static> query()
 * @method static Builder<static> active()
 */
class PaymentGateway extends Model
{
    /** @use HasFactory<PaymentGatewayFactory> */
    use CentralConnection;

    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'driver',
        'priority',
        'is_active',
        'is_fallback',
        'supports_subscription',
        'supports_refund',
        'supports_webhook',
        'supports_partial_refund',
        'config',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'priority' => 100,
        'is_active' => true,
        'is_fallback' => false,
        'supports_subscription' => false,
        'supports_refund' => false,
        'supports_webhook' => false,
        'supports_partial_refund' => false,
    ];

    protected static function newFactory(): PaymentGatewayFactory
    {
        return PaymentGatewayFactory::new();
    }

    /**
     * Currencies this gateway accepts.
     *
     * @return BelongsToMany<Currency, $this>
     */
    public function currencies(): BelongsToMany
    {
        return $this->belongsToMany(Currency::class, 'payment_gateway_currencies')
            ->withPivot(['is_default'])
            ->withTimestamps();
    }

    /**
     * Countries where this gateway is preferred / available.
     *
     * @return BelongsToMany<Country, $this>
     */
    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'payment_gateway_countries')
            ->withPivot(['priority'])
            ->withTimestamps();
    }

    /**
     * Environment-specific credentials for this gateway.
     *
     * @return HasMany<PaymentGatewayConfig, $this>
     */
    public function configs(): HasMany
    {
        return $this->hasMany(PaymentGatewayConfig::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'is_active' => 'boolean',
            'is_fallback' => 'boolean',
            'supports_subscription' => 'boolean',
            'supports_refund' => 'boolean',
            'supports_webhook' => 'boolean',
            'supports_partial_refund' => 'boolean',
            'config' => 'array',
        ];
    }
}
