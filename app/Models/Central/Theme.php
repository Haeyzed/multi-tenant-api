<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\ThemeStatus;
use Database\Factories\Central\ThemeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Theme package available for tenant storefronts.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $version
 * @property ThemeStatus $status
 * @property string|null $preview_url
 * @property string $price
 * @property string|null $author
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, ThemeInstallation> $installations
 *
 * @method static Builder<static> query()
 */
class Theme extends Model
{
    /** @use HasFactory<ThemeFactory> */
    use CentralConnection, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'version',
        'status',
        'preview_url',
        'price',
        'author',
        'metadata',
    ];

    protected static function newFactory(): ThemeFactory
    {
        return ThemeFactory::new();
    }

    /**
     * Tenant installations of this theme.
     *
     * @return HasMany<ThemeInstallation, $this>
     */
    public function installations(): HasMany
    {
        return $this->hasMany(ThemeInstallation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ThemeStatus::class,
            'price' => 'decimal:2',
            'metadata' => 'array',
        ];
    }
}
