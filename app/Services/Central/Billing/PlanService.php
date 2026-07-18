<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Enums\Central\PlanFeatureLimitType;
use App\Enums\Central\PlanStatus;
use App\Enums\Central\PlanVisibility;
use App\Enums\Central\SubscriptionInterval;
use App\Models\Central\Feature;
use App\Models\Central\Plan;
use App\Models\Central\PlanFeature;
use App\Models\Central\PlanPrice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Service responsible for managing subscription plan lifecycle operations.
 *
 * Encapsulates plan CRUD, feature synchronization, status transitions, and
 * soft-delete restoration so controllers remain thin.
 */
final class PlanService
{
    public function __construct(
        private readonly PlanPriceResolver $priceResolver,
        private readonly BillingSettings   $billingSettings,
    )
    {
    }

    /**
     * Aggregate counts for the plans index.
     *
     * @return array{
     *     total: int,
     *     draft: int,
     *     active: int,
     *     inactive: int,
     *     archived: int,
     *     public: int,
     *     featured: int,
     *     by_status: array<string, int>,
     *     by_visibility: array<string, int>
     * }
     */
    public function overviewStatistics(): array
    {
        $byStatus = Plan::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn($count): int => (int)$count)
            ->all();

        $byVisibility = Plan::query()
            ->selectRaw('visibility, COUNT(*) as aggregate')
            ->groupBy('visibility')
            ->pluck('aggregate', 'visibility')
            ->map(fn($count): int => (int)$count)
            ->all();

        return [
            'total' => (int)array_sum($byStatus),
            'draft' => (int)($byStatus[PlanStatus::Draft->value] ?? 0),
            'active' => (int)($byStatus[PlanStatus::Active->value] ?? 0),
            'inactive' => (int)($byStatus[PlanStatus::Inactive->value] ?? 0),
            'archived' => (int)($byStatus[PlanStatus::Archived->value] ?? 0),
            'public' => (int)($byVisibility[PlanVisibility::Public->value] ?? 0),
            'featured' => (int)Plan::query()->where('is_featured', true)->count(),
            'by_status' => $byStatus,
            'by_visibility' => $byVisibility,
        ];
    }

    /**
     * Paginate publicly selectable plans for self-serve signup.
     *
     * @param array{per_page?: int, country?: string, currency?: string, interval?: string} $filters
     * @return LengthAwarePaginator<int, Plan>
     */
    public function paginatePublic(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int)($filters['per_page'] ?? 15), 100);

        $plans = Plan::query()
            ->with(['prices' => fn($q) => $q->where('status', PlanStatus::Active)])
            ->withCount('features')
            ->where('status', PlanStatus::Active)
            ->where('visibility', PlanVisibility::Public)
            ->orderBy('sort_order')
            ->orderBy('price')
            ->paginate($perPage);

        if (filled($filters['country'] ?? null) || filled($filters['currency'] ?? null)) {
            $plans->getCollection()->transform(function (Plan $plan) use ($filters): Plan {
                try {
                    $resolved = $this->priceResolver->resolve(
                        $plan,
                        isset($filters['country']) ? (string)$filters['country'] : null,
                        isset($filters['currency']) ? (string)$filters['currency'] : null,
                        $filters['interval'] ?? null,
                    );
                    $plan->setAttribute('resolved_price', $resolved);
                } catch (ValidationException) {
                    $plan->setAttribute('resolved_price', null);
                }

                return $plan;
            });
        }

        return $plans;
    }

    /**
     * Paginate plans with optional search and filter criteria.
     *
     * @param array{search?: string, status?: string, visibility?: string, interval?: string, per_page?: int} $filters
     * @return LengthAwarePaginator<int, Plan>
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int)($filters['per_page'] ?? 15), 100);

        return Plan::query()
            ->with(['prices'])
            ->withCount('features')
            ->when(
                $filters['search'] ?? null,
                fn($query, string $search) => $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                })
            )
            ->when(
                $filters['status'] ?? null,
                fn($query, string $status) => $query->where('status', $status)
            )
            ->when(
                $filters['visibility'] ?? null,
                fn($query, string $visibility) => $query->where('visibility', $visibility)
            )
            ->when(
                $filters['interval'] ?? null,
                fn($query, string $interval) => $query->where('billing_interval', $interval)
            )
            ->orderBy('sort_order')
            ->orderBy('price')
            ->paginate($perPage);
    }

    /**
     * Build dropdown options for publicly selectable plans.
     *
     * @param array{country?: string, currency?: string, interval?: string} $filters
     * @return list<array{value: int, label: string, currency?: string, amount?: string, billing_interval?: string}>
     */
    public function optionsForDropdown(array $filters = []): array
    {
        $plans = Plan::query()
            ->with(['prices' => fn($q) => $q->where('status', PlanStatus::Active)])
            ->where('status', PlanStatus::Active)
            ->where('visibility', PlanVisibility::Public)
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get();

        return $plans
            ->map(function (Plan $plan) use ($filters): array {
                $price = null;

                if (filled($filters['country'] ?? null) || filled($filters['currency'] ?? null) || $plan->prices->isNotEmpty()) {
                    try {
                        $price = $this->priceResolver->resolve(
                            $plan,
                            isset($filters['country']) ? (string)$filters['country'] : null,
                            isset($filters['currency']) ? (string)$filters['currency'] : null,
                            $filters['interval'] ?? null,
                        );
                    } catch (ValidationException) {
                        $price = null;
                    }
                }

                return [
                    'value' => $plan->id,
                    'label' => $this->dropdownLabel($plan, $price),
                    'currency' => $price?->currency ?? $plan->currency,
                    'amount' => $price?->amount ?? $plan->price,
                    'billing_interval' => ($price?->billing_interval ?? $plan->billing_interval)?->value,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Format a plan as a dropdown label (e.g. "Pro — NGN 29.00/mo").
     */
    public function dropdownLabel(Plan $plan, ?PlanPrice $price = null): string
    {
        $amount = number_format((float)($price?->amount ?? $plan->price), 2, '.', '');
        $currency = (string)($price?->currency ?? $plan->currency);
        $interval = $price?->billing_interval ?? $plan->billing_interval;
        $suffix = match ($interval) {
            SubscriptionInterval::MONTHLY => 'mo',
            SubscriptionInterval::QUARTERLY => 'qtr',
            SubscriptionInterval::YEARLY => 'yr',
            default => $interval?->value ?? 'mo',
        };

        return "{$plan->name} — {$currency} {$amount}/{$suffix}";
    }

    /**
     * Create a new plan with optional feature assignments.
     *
     * Generates a unique slug when none is provided and applies sensible
     * defaults for pricing, billing interval, and visibility.
     *
     * @param array<string, mixed> $data
     * @return Plan
     * @throws Throwable
     */
    public function create(array $data): Plan
    {
        return DB::transaction(function () use ($data): Plan {
            $slug = $data['slug'] ?? Str::slug($data['name']);
            $features = $data['features'] ?? null;
            $prices = $data['prices'] ?? null;
            unset($data['features'], $data['prices']);

            $plan = Plan::query()->create([
                'price' => 0,
                'currency' => 'USD',
                'billing_interval' => $this->billingSettings->defaultInterval()->value,
                'trial_days' => 0,
                'status' => PlanStatus::Draft->value,
                'visibility' => 'private',
                'is_featured' => false,
                'sort_order' => 0,
                ...$data,
                'slug' => $this->uniqueSlug($slug),
            ]);

            if (!empty($features) && is_array($features)) {
                $this->syncFeatures($plan, $features);
            }

            if (is_array($prices) && $prices !== []) {
                $this->syncPrices($plan, $prices);
            } elseif ((float)$plan->price > 0 || filled($plan->currency)) {
                $this->upsertPrice($plan, [
                    'amount' => $plan->price,
                    'currency' => $plan->currency,
                    'billing_interval' => $plan->billing_interval?->value ?? 'monthly',
                    'trial_days' => $plan->trial_days,
                    'status' => PlanStatus::Active->value,
                ]);
            }

            $this->syncLegacyPricingFromPrimary($plan);

            return $plan->load(['features.category', 'prices'])->loadCount('features');
        });
    }

    /**
     * Generate a unique plan slug.
     *
     * Appends an incrementing suffix when the base slug is already taken,
     * including soft-deleted records, optionally ignoring a plan ID during updates.
     *
     * @param string $slug
     * @param int|null $ignoreId
     * @return string
     */
    private function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug);
        $candidate = $base !== '' ? $base : 'plan';
        $i = 1;

        while (
        Plan::withTrashed()
            ->where('slug', $candidate)
            ->when($ignoreId, fn($q) => $q->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $candidate = $base . '-' . $i;
            $i++;
        }

        return $candidate;
    }

    /**
     * Synchronize the features attached to a plan.
     *
     * Validates that each feature exists and maps limit configuration onto
     * the plan-feature pivot before replacing the full feature set.
     *
     * @param Plan $plan
     * @param list<array{feature_id: int, limit_type?: string, limit_value?: int|null, is_unlimited?: bool, is_enabled?: bool, tracks_usage?: bool, reset_period?: string|null, metadata?: array<string, mixed>}> $features
     * @return Plan
     *
     * @throws ValidationException|Throwable
     */
    public function syncFeatures(Plan $plan, array $features): Plan
    {
        return DB::transaction(function () use ($plan, $features): Plan {
            $sync = [];

            foreach ($features as $item) {
                $featureId = (int)$item['feature_id'];

                if (!Feature::query()->whereKey($featureId)->exists()) {
                    throw ValidationException::withMessages([
                        'features' => ["Feature [{$featureId}] does not exist."],
                    ]);
                }

                $limitType = $item['limit_type'] ?? PlanFeatureLimitType::BOOLEAN->value;
                $isUnlimited = (bool)($item['is_unlimited'] ?? $limitType === PlanFeatureLimitType::UNLIMITED->value);

                $sync[$featureId] = [
                    'limit_type' => $limitType,
                    'limit_value' => $isUnlimited ? null : ($item['limit_value'] ?? null),
                    'is_unlimited' => $isUnlimited,
                    'is_enabled' => $item['is_enabled'] ?? true,
                    'tracks_usage' => $item['tracks_usage'] ?? false,
                    'reset_period' => $item['reset_period'] ?? null,
                    'metadata' => isset($item['metadata']) ? json_encode($item['metadata']) : null,
                ];
            }

            $plan->features()->sync($sync);

            return $plan->fresh(['features.category'])->loadCount('features');
        });
    }

    /**
     * Replace all prices for a plan.
     *
     * @param list<array{amount: float|int|string, currency: string, billing_interval?: string, trial_days?: int|null, status?: string, metadata?: array<string, mixed>|null}> $prices
     */
    public function syncPrices(Plan $plan, array $prices): Plan
    {
        return DB::transaction(function () use ($plan, $prices): Plan {
            $keepIds = [];

            foreach ($prices as $row) {
                $price = $this->upsertPrice($plan, $row);
                $keepIds[] = $price->id;
            }

            $plan->prices()->whereNotIn('id', $keepIds)->delete();
            $this->syncLegacyPricingFromPrimary($plan->fresh());

            return $plan->fresh(['prices']);
        });
    }

    /**
     * @param array{amount: float|int|string, currency: string, billing_interval?: string, trial_days?: int|null, status?: string, metadata?: array<string, mixed>|null, id?: int} $data
     */
    public function upsertPrice(Plan $plan, array $data): PlanPrice
    {
        $currency = Str::upper((string)$data['currency']);
        $interval = (string)($data['billing_interval'] ?? SubscriptionInterval::MONTHLY->value);

        $attributes = [
            'amount' => $data['amount'],
            'trial_days' => $data['trial_days'] ?? null,
            'status' => $data['status'] ?? PlanStatus::Active->value,
            'metadata' => $data['metadata'] ?? null,
        ];

        if (isset($data['id'])) {
            $price = $plan->prices()->whereKey($data['id'])->firstOrFail();
            $price->update([
                ...$attributes,
                'currency' => $currency,
                'billing_interval' => $interval,
            ]);

            return $price->fresh();
        }

        return $plan->prices()->updateOrCreate(
            [
                'currency' => $currency,
                'billing_interval' => $interval,
            ],
            $attributes,
        );
    }

    /**
     * Update an existing plan and optionally resync its features.
     *
     * Ensures slug uniqueness when the slug changes and delegates feature
     * replacement to {@see syncFeatures()} when a features array is provided.
     *
     * @param Plan $plan
     * @param array<string, mixed> $data
     * @return Plan
     *
     * @throws ValidationException|Throwable
     */
    public function update(Plan $plan, array $data): Plan
    {
        return DB::transaction(function () use ($plan, $data): Plan {
            if (isset($data['slug']) && $data['slug'] !== $plan->slug) {
                $data['slug'] = $this->uniqueSlug($data['slug'], $plan->id);
            }

            $prices = $data['prices'] ?? null;
            $plan->update(collect($data)->except(['features', 'prices'])->all());

            if (array_key_exists('features', $data) && is_array($data['features'])) {
                $this->syncFeatures($plan, $data['features']);
            }

            if (is_array($prices)) {
                $this->syncPrices($plan, $prices);
            } elseif (
                array_key_exists('price', $data)
                || array_key_exists('currency', $data)
                || array_key_exists('billing_interval', $data)
                || array_key_exists('trial_days', $data)
            ) {
                $this->upsertPrice($plan->fresh(), [
                    'amount' => $plan->fresh()->price,
                    'currency' => $plan->fresh()->currency,
                    'billing_interval' => $plan->fresh()->billing_interval?->value ?? 'monthly',
                    'trial_days' => $plan->fresh()->trial_days,
                    'status' => PlanStatus::Active->value,
                ]);
            }

            $this->syncLegacyPricingFromPrimary($plan->fresh());

            return $plan->fresh(['features.category', 'prices'])->loadCount('features');
        });
    }

    /**
     * Keep legacy plan.price/currency in sync with the first active price.
     */
    private function syncLegacyPricingFromPrimary(Plan $plan): void
    {
        $primary = $plan->prices()
            ->where('status', PlanStatus::Active)
            ->orderByRaw("CASE WHEN currency = ? THEN 0 ELSE 1 END", [(string)$plan->currency])
            ->orderBy('id')
            ->first()
            ?? $plan->prices()->orderBy('id')->first();

        if ($primary === null) {
            return;
        }

        $plan->update([
            'price' => $primary->amount,
            'currency' => $primary->currency,
            'billing_interval' => $primary->billing_interval,
            'trial_days' => $primary->trial_days ?? $plan->trial_days,
        ]);
    }

    /**
     * Soft-delete the specified plan.
     *
     * @param Plan $plan
     */
    public function delete(Plan $plan): void
    {
        $plan->delete();
    }

    /**
     * Soft-delete multiple plans by ID.
     *
     * @param list<int> $ids
     * @return int
     */
    public function deleteMany(array $ids): int
    {
        $plans = Plan::query()->whereIn('id', $ids)->get();

        $plans->each(fn(Plan $plan) => $this->delete($plan));

        return $plans->count();
    }

    /**
     * Activate multiple plans by ID.
     *
     * @param list<int> $ids
     * @return int
     */
    public function activateMany(array $ids): int
    {
        return Plan::query()
            ->whereIn('id', $ids)
            ->update(['status' => PlanStatus::Active]);
    }

    /**
     * Archive multiple plans by ID.
     *
     * @param list<int> $ids
     * @return int
     */
    public function archiveMany(array $ids): int
    {
        return Plan::query()
            ->whereIn('id', $ids)
            ->update(['status' => PlanStatus::Archived]);
    }

    /**
     * Restore a soft-deleted plan.
     *
     * @param Plan $plan
     * @return Plan
     */
    public function restore(Plan $plan): Plan
    {
        $plan->restore();

        return $plan->fresh(['features.category'])->loadCount('features');
    }

    /**
     * Activate the specified plan.
     *
     * @param Plan $plan
     * @return Plan
     */
    public function activate(Plan $plan): Plan
    {
        $plan->update(['status' => PlanStatus::Active]);

        return $plan->fresh(['features.category']);
    }

    public function deletePrice(Plan $plan, PlanPrice $price): void
    {
        if ($price->plan_id !== $plan->id) {
            throw ValidationException::withMessages([
                'plan_price' => ['Price does not belong to this plan.'],
            ]);
        }

        $price->delete();
        $this->syncLegacyPricingFromPrimary($plan->fresh());
    }

    /**
     * Archive the specified plan.
     *
     * @param Plan $plan
     * @return Plan
     */
    public function archive(Plan $plan): Plan
    {
        $plan->update(['status' => PlanStatus::Archived]);

        return $plan->fresh(['features.category']);
    }

    /**
     * Attach or update a single feature on a plan.
     *
     * Applies feature defaults when limit configuration is omitted and
     * returns the hydrated plan-feature pivot record.
     *
     * @param Plan $plan
     * @param Feature $feature
     * @param array<string, mixed> $data
     * @return PlanFeature
     */
    public function assignFeature(Plan $plan, Feature $feature, array $data = []): PlanFeature
    {
        $limitType = $data['limit_type'] ?? $feature->default_limit_type?->value ?? PlanFeatureLimitType::BOOLEAN->value;
        $isUnlimited = (bool)($data['is_unlimited'] ?? $limitType === PlanFeatureLimitType::UNLIMITED->value);

        $plan->features()->syncWithoutDetaching([
            $feature->id => [
                'limit_type' => $limitType,
                'limit_value' => $isUnlimited ? null : ($data['limit_value'] ?? $feature->default_limit_value),
                'is_unlimited' => $isUnlimited,
                'is_enabled' => $data['is_enabled'] ?? true,
                'tracks_usage' => $data['tracks_usage'] ?? $feature->tracks_usage,
                'reset_period' => $data['reset_period'] ?? null,
                'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            ],
        ]);

        /** @var PlanFeature $pivot */
        $pivot = PlanFeature::query()
            ->where('plan_id', $plan->id)
            ->where('feature_id', $feature->id)
            ->firstOrFail();

        return $pivot->load(['feature', 'plan']);
    }

    /**
     * Detach a feature from the specified plan.
     *
     * @param Plan $plan
     * @param Feature $feature
     */
    public function detachFeature(Plan $plan, Feature $feature): void
    {
        $plan->features()->detach($feature->id);
    }
}
