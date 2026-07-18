<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Models\User;
use Database\Factories\Central\ThemeInstallationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Tenant-specific installation and activation of a theme.
 *
 * @property int $id
 * @property int $theme_id
 * @property string|null $tenant_id
 * @property bool $is_active
 * @property string|null $installed_version
 * @property Carbon|null $activated_at
 * @property int|null $installed_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Theme $theme
 * @property-read Tenant|null $tenant
 * @property-read User|null $installer
 *
 * @method static Builder<static> query()
 */
class ThemeInstallation extends Model
{
    /** @use HasFactory<ThemeInstallationFactory> */
    use CentralConnection, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'theme_id',
        'tenant_id',
        'is_active',
        'installed_version',
        'activated_at',
        'installed_by',
    ];

    protected static function newFactory(): ThemeInstallationFactory
    {
        return ThemeInstallationFactory::new();
    }

    /**
     * Theme being installed.
     *
     * @return BelongsTo<Theme, $this>
     */
    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    /**
     * Tenant that installed the theme.
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
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
        ];
    }
}
