<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Central\Tenant as CentralTenant;
use App\Models\Tenant\Brand;
use App\Services\Central\Billing\EntitlementService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Tenant-scoped brand catalog with plan feature entitlement checks.
 */
final class BrandService
{
    public const FEATURE_KEY = 'brands';

    public function __construct(
        private readonly EntitlementService $entitlements,
    ) {}

    /**
     * @param  array{search?: string, is_visible?: list<string>|string, is_featured?: bool, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, Brand>
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return Brand::query()
            ->when(
                $filters['search'] ?? null,
                fn ($query, string $search) => $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                }),
            )
            ->when(
                isset($filters['is_visible']),
                function ($query) use ($filters): void {
                    $values = is_array($filters['is_visible'])
                        ? $filters['is_visible']
                        : [(string) $filters['is_visible']];

                    if (in_array('visible', $values, true) && ! in_array('hidden', $values, true)) {
                        $query->where('is_visible', true);
                    } elseif (in_array('hidden', $values, true) && ! in_array('visible', $values, true)) {
                        $query->where('is_visible', false);
                    }
                },
            )
            ->when(
                array_key_exists('is_featured', $filters) && $filters['is_featured'] !== null,
                fn ($query) => $query->where('is_featured', filter_var($filters['is_featured'], FILTER_VALIDATE_BOOLEAN)),
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * @return list<array{label: string, value: int, image_url: null}>
     */
    public function options(): array
    {
        return Brand::query()
            ->where('is_visible', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Brand $brand): array => [
                'label' => $brand->name,
                'value' => $brand->id,
                'image_url' => null,
            ])
            ->all();
    }

    /**
     * @return array{total: int, visible: int, hidden: int}
     */
    public function statistics(): array
    {
        $total = Brand::query()->count();
        $visible = Brand::query()->where('is_visible', true)->count();

        return [
            'total' => $total,
            'visible' => $visible,
            'hidden' => max(0, $total - $visible),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException|Throwable
     */
    public function create(array $data): Brand
    {
        /** @var CentralTenant $tenant */
        $tenant = tenant();

        $this->entitlements->consume($tenant, self::FEATURE_KEY);

        try {
            if (blank($data['slug'] ?? null)) {
                $data['slug'] = Brand::uniqueSlug((string) $data['name']);
            }

            $data['is_visible'] ??= true;
            $data['is_featured'] ??= false;
            $data['sort_order'] ??= 0;

            return Brand::query()->create($data);
        } catch (Throwable $exception) {
            $this->entitlements->release($tenant, self::FEATURE_KEY);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Brand $brand, array $data): Brand
    {
        if (isset($data['name']) && blank($data['slug'] ?? null) && $data['name'] !== $brand->name) {
            $data['slug'] = Brand::uniqueSlug((string) $data['name'], $brand->id);
        }

        $brand->update($data);

        return $brand->fresh();
    }

    public function delete(Brand $brand): void
    {
        /** @var CentralTenant $tenant */
        $tenant = tenant();

        $brand->delete();
        $this->entitlements->release($tenant, self::FEATURE_KEY);
    }

    /**
     * @param  list<int>  $ids
     */
    public function deleteMany(array $ids): int
    {
        $deleted = 0;

        foreach (Brand::query()->whereIn('id', $ids)->get() as $brand) {
            $this->delete($brand);
            $deleted++;
        }

        return $deleted;
    }

    public function toggleVisibility(Brand $brand): Brand
    {
        $brand->update(['is_visible' => ! $brand->is_visible]);

        return $brand->fresh();
    }

    public function toggleFeatured(Brand $brand): Brand
    {
        $brand->update(['is_featured' => ! $brand->is_featured]);

        return $brand->fresh();
    }

    /**
     * @param  list<int>  $ids
     */
    public function reorder(array $ids): void
    {
        foreach ($ids as $index => $id) {
            Brand::query()->whereKey($id)->update(['sort_order' => $index]);
        }
    }

    public function findBySlug(string $slug): Brand
    {
        return Brand::query()->where('slug', $slug)->firstOrFail();
    }
}
