<?php

declare(strict_types=1);

namespace App\Models\Central;

use Database\Factories\Central\PaymentGatewayConfigFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Environment-specific credentials for a payment gateway.
 */
class PaymentGatewayConfig extends Model
{
    /** @use HasFactory<PaymentGatewayConfigFactory> */
    use CentralConnection;

    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payment_gateway_id',
        'environment',
        'public_key',
        'secret_key',
        'webhook_secret',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'environment' => 'test',
        'is_active' => true,
    ];

    protected static function newFactory(): PaymentGatewayConfigFactory
    {
        return PaymentGatewayConfigFactory::new();
    }

    /**
     * @return BelongsTo<PaymentGateway, $this>
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'secret_key' => 'encrypted',
            'webhook_secret' => 'encrypted',
        ];
    }
}
