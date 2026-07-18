<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\ApiKeyType;
use App\Models\User;
use Database\Factories\Central\ApiClientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * API client credentials for programmatic platform access.
 *
 * @property int $id
 * @property string $name
 * @property string $client_id
 * @property string|null $client_secret
 * @property ApiKeyType $type
 * @property list<string>|null $scopes
 * @property int $rate_limit_per_minute
 * @property bool $is_active
 * @property Carbon|null $last_used_at
 * @property int|null $created_by
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $creator
 * @property-read Collection<int, Webhook> $webhooks
 *
 * @method static Builder<static> query()
 */
class ApiClient extends Model
{
    /** @use HasFactory<ApiClientFactory> */
    use CentralConnection, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'client_id',
        'client_secret',
        'type',
        'scopes',
        'rate_limit_per_minute',
        'is_active',
        'last_used_at',
        'created_by',
        'metadata',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'client_secret',
    ];

    protected static function newFactory(): ApiClientFactory
    {
        return ApiClientFactory::new();
    }

    /**
     * Platform user who created this API client.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Webhooks owned by this API client.
     *
     * @return HasMany<Webhook, $this>
     */
    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ApiKeyType::class,
            'scopes' => 'array',
            'rate_limit_per_minute' => 'integer',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
            'client_secret' => 'encrypted',
            'metadata' => 'array',
        ];
    }
}
