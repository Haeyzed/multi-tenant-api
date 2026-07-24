<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\BrandFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Tenant product brand.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $summary
 * @property bool $is_visible
 * @property bool $is_featured
 * @property int|null $logo_media_id
 * @property int|null $banner_media_id
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $website_url
 * @property string|null $country_of_origin
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @method static Builder<static> query()
 */
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'summary',
        'is_visible',
        'is_featured',
        'logo_media_id',
        'banner_media_id',
        'meta_title',
        'meta_description',
        'website_url',
        'country_of_origin',
        'sort_order',
    ];

    protected static function newFactory(): BrandFactory
    {
        return BrandFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Brand $brand): void {
            if (blank($brand->slug)) {
                $brand->slug = static::uniqueSlug((string) $brand->name);
            }
        });
    }

    public static function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source);
        $candidate = $base !== '' ? $base : 'brand';
        $i = 1;

        while (
            static::withTrashed()
                ->where('slug', $candidate)
                ->when($ignoreId, fn (Builder $q) => $q->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $candidate = $base.'-'.$i;
            $i++;
        }

        return $candidate;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'logo_media_id' => 'integer',
            'banner_media_id' => 'integer',
        ];
    }
}
