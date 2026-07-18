<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\IntegrationStatus;
use Database\Factories\Central\IntegrationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Third-party integration available in the platform marketplace.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $vendor
 * @property string|null $description
 * @property string $version
 * @property IntegrationStatus $status
 * @property bool $is_marketplace
 * @property string $price
 * @property list<string>|null $permissions
 * @property array<string, mixed>|null $config_schema
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, InstalledIntegration> $installations
 *
 * @method static Builder<static> query()
 */
class Integration extends Model
{
    /** @use HasFactory<IntegrationFactory> */
    use CentralConnection, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'vendor',
        'description',
        'version',
        'status',
        'is_marketplace',
        'price',
        'permissions',
        'config_schema',
        'metadata',
    ];

    protected static function newFactory(): IntegrationFactory
    {
        return IntegrationFactory::new();
    }

    /**
     * Tenant installations of this integration.
     *
     * @return HasMany<InstalledIntegration, $this>
     */
    public function installations(): HasMany
    {
        return $this->hasMany(InstalledIntegration::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => IntegrationStatus::class,
            'is_marketplace' => 'boolean',
            'price' => 'decimal:2',
            'permissions' => 'array',
            'config_schema' => 'array',
            'metadata' => 'array',
        ];
    }
}
