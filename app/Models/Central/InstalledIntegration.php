<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\IntegrationStatus;
use App\Models\User;
use Database\Factories\Central\InstalledIntegrationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Tenant-specific installation of a marketplace integration.
 *
 * @property int $id
 * @property int $integration_id
 * @property string|null $tenant_id
 * @property IntegrationStatus $status
 * @property string|null $installed_version
 * @property array<string, mixed>|null $configuration
 * @property Carbon|null $activated_at
 * @property int|null $installed_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Integration $integration
 * @property-read Tenant|null $tenant
 * @property-read User|null $installer
 *
 * @method static Builder<static> query()
 */
class InstalledIntegration extends Model
{
    /** @use HasFactory<InstalledIntegrationFactory> */
    use CentralConnection, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'integration_id',
        'tenant_id',
        'status',
        'installed_version',
        'configuration',
        'activated_at',
        'installed_by',
    ];

    protected static function newFactory(): InstalledIntegrationFactory
    {
        return InstalledIntegrationFactory::new();
    }

    /**
     * Integration definition being installed.
     *
     * @return BelongsTo<Integration, $this>
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /**
     * Tenant that installed the integration.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * User who performed the installation.
     *
     * @return BelongsTo<User, $this>
     */
    public function installer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installed_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => IntegrationStatus::class,
            'configuration' => 'array',
            'activated_at' => 'datetime',
        ];
    }
}
