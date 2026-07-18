<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Enums\Central\FeatureStatus;
use App\Enums\Central\PlanFeatureLimitType;
use App\Models\Central\Feature;
use App\Models\Central\FeatureCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Service responsible for managing billable features and feature categories.
 *
 * Encapsulates feature and category CRUD, slug and key uniqueness, soft-delete
 * restoration, and category listing so controllers remain thin.
 */
final class FeatureService
{
    /**
     * Paginate features with optional search and filter criteria.
     *
     * @param array{search?: string, status?: string, category_id?: int, available?: bool, per_page?: int} $filters
     * @return LengthAwarePaginator<int, Feature>
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int)($filters['per_page'] ?? 15), 100);

        return Feature::query()
            ->with('category')
            ->when(
                $filters['search'] ?? null,
                fn($query, string $search) => $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('key', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                })
            )
            ->when(
                $filters['status'] ?? null,
                fn($query, string $status) => $query->where('status', $status)
            )
            ->when(
                $filters['category_id'] ?? null,
                fn($query, int $categoryId) => $query->where('feature_category_id', $categoryId)
            )
            ->when(
                array_key_exists('available', $filters) && $filters['available'] !== null,
                fn($query) => $query->where('is_available', filter_var($filters['available'], FILTER_VALIDATE_BOOLEAN))
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Restore a soft-deleted feature.
     *
     * @param Feature $feature
     * @return Feature
     */
    public function restore(Feature $feature): Feature
    {
        $feature->restore();

        return $feature->fresh('category');
    }

    /**
     * Create a new feature category.
     *
     * Generates a unique slug when none is provided.
     *
     * @param array<string, mixed> $data
     * @return FeatureCategory
     */
    public function createCategory(array $data): FeatureCategory
    {
        $slug = $data['slug'] ?? Str::slug($data['name']);

        return FeatureCategory::query()->create([
            ...$data,
            'slug' => $this->uniqueCategorySlug($slug),
        ]);
    }

    /**
     * Create a new billable feature.
     *
     * Generates unique slug and key values when omitted and applies sensible
     * defaults for status, limit type, and availability.
     *
     * @param array<string, mixed> $data
     * @return Feature
     * @throws Throwable
     */
    public function create(array $data): Feature
    {
        return DB::transaction(function () use ($data): Feature {
            $slug = $data['slug'] ?? Str::slug($data['name']);
            $key = $data['key'] ?? Str::snake($slug);

            return Feature::query()->create([
                'status' => FeatureStatus::Active->value,
                'default_limit_type' => PlanFeatureLimitType::BOOLEAN->value,
                'is_available' => true,
                'tracks_usage' => false,
                'sort_order' => 0,
                ...$data,
                'slug' => $this->uniqueSlug($slug),
                'key' => $this->uniqueKey($key),
            ])->load('category');
        });
    }

    /**
     * Generate a unique feature slug.
     *
     * @param string $slug
     * @param int|null $ignoreId
     * @return string
     */
    private function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        return $this->uniqueValue(Feature::class, 'slug', Str::slug($slug), $ignoreId);
    }

    /**
     * Generate a unique value for the given model column.
     *
     * Appends an incrementing suffix when the base value is already taken,
     * including soft-deleted records when the model supports soft deletes.
     *
     * @param class-string<Model> $model
     * @param string $column
     * @param string $base
     * @param int|null $ignoreId
     * @return string
     */
    private function uniqueValue(string $model, string $column, string $base, ?int $ignoreId = null): string
    {
        $candidate = $base !== '' ? $base : 'item';
        $i = 1;

        while (
        $model::query()
            ->when(
                in_array(SoftDeletes::class, class_uses_recursive($model), true),
                fn($q) => $q->withTrashed()
            )
            ->where($column, $candidate)
            ->when($ignoreId, fn($q) => $q->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $candidate = $base . '-' . $i;
            $i++;
        }

        return $candidate;
    }

    /**
     * Generate a unique feature key.
     *
     * @param string $key
     * @param int|null $ignoreId
     * @return string
     */
    private function uniqueKey(string $key, ?int $ignoreId = null): string
    {
        return $this->uniqueValue(Feature::class, 'key', Str::snake($key), $ignoreId);
    }

    /**
     * Generate a unique feature category slug.
     *
     * @param string $slug
     * @param int|null $ignoreId
     * @return string
     */
    private function uniqueCategorySlug(string $slug, ?int $ignoreId = null): string
    {
        return $this->uniqueValue(FeatureCategory::class, 'slug', Str::slug($slug), $ignoreId);
    }

    /**
     * Update an existing feature category.
     *
     * Ensures slug uniqueness when the slug changes.
     *
     * @param FeatureCategory $category
     * @param array<string, mixed> $data
     * @return FeatureCategory
     */
    public function updateCategory(FeatureCategory $category, array $data): FeatureCategory
    {
        if (isset($data['slug']) && $data['slug'] !== $category->slug) {
            $data['slug'] = $this->uniqueCategorySlug($data['slug'], $category->id);
        }

        $category->update($data);

        return $category->fresh();
    }

    /**
     * Update an existing feature.
     *
     * Ensures slug and key uniqueness when either identifier changes.
     *
     * @param Feature $feature
     * @param array<string, mixed> $data
     * @return Feature
     */
    public function update(Feature $feature, array $data): Feature
    {
        if (isset($data['slug']) && $data['slug'] !== $feature->slug) {
            $data['slug'] = $this->uniqueSlug($data['slug'], $feature->id);
        }

        if (isset($data['key']) && $data['key'] !== $feature->key) {
            $data['key'] = $this->uniqueKey($data['key'], $feature->id);
        }

        $feature->update($data);

        return $feature->fresh('category');
    }

    /**
     * Delete a feature category that has no assigned features.
     *
     * @param FeatureCategory $category
     *
     * @throws ValidationException
     */
    public function deleteCategory(FeatureCategory $category): void
    {
        if ($category->features()->exists()) {
            throw ValidationException::withMessages([
                'category' => ['Cannot delete a category that still has features.'],
            ]);
        }

        $category->delete();
    }

    /**
     * Soft-delete the specified feature.
     *
     * @param Feature $feature
     */
    public function delete(Feature $feature): void
    {
        $feature->delete();
    }

    /**
     * Build dropdown options for feature categories.
     *
     * @return list<array{value: int, label: string}>
     */
    public function categoryOptionsForDropdown(bool $activeOnly = true): array
    {
        return $this->listCategories($activeOnly)
            ->map(fn(FeatureCategory $category): array => [
                'value' => $category->id,
                'label' => $category->name,
            ])
            ->values()
            ->all();
    }

    /**
     * List feature categories ordered for display.
     *
     * @param bool $activeOnly
     * @return Collection<int, FeatureCategory>
     */
    public function listCategories(bool $activeOnly = false): Collection
    {
        return FeatureCategory::query()
            ->withCount('features')
            ->when($activeOnly, fn($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
