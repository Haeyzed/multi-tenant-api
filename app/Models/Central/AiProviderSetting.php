<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\AIProvider;
use Database\Factories\Central\AiProviderSettingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Configuration and usage limits for an AI provider integration.
 *
 * @property int $id
 * @property AIProvider $provider
 * @property string $label
 * @property bool $is_enabled
 * @property string|null $api_key
 * @property string|null $default_model
 * @property int|null $monthly_token_limit
 * @property int $monthly_token_usage
 * @property string $credits_remaining
 * @property array<string, mixed>|null $config
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 */
class AiProviderSetting extends Model
{
    /** @use HasFactory<AiProviderSettingFactory> */
    use CentralConnection, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider',
        'label',
        'is_enabled',
        'api_key',
        'default_model',
        'monthly_token_limit',
        'monthly_token_usage',
        'credits_remaining',
        'config',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'api_key',
    ];

    protected static function newFactory(): AiProviderSettingFactory
    {
        return AiProviderSettingFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => AIProvider::class,
            'is_enabled' => 'boolean',
            'monthly_token_limit' => 'integer',
            'monthly_token_usage' => 'integer',
            'credits_remaining' => 'decimal:2',
            'api_key' => 'encrypted',
            'config' => 'array',
        ];
    }
}
