<?php

declare(strict_types=1);

namespace App\Services\Central\Settings;

use App\Enums\Central\SettingGroup;
use App\Enums\Central\SettingType;
use App\Models\Central\Setting;
use App\Services\Central\Billing\BillingSettingsPolicy;
use App\Services\Central\Billing\PaymentSettingsPolicy;
use App\Services\Central\Tenants\TenantSettingsPolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

/**
 * Service responsible for central platform settings management.
 *
 * Encapsulates CRUD, bulk updates, typed value serialization, caching,
 * and grouped listing so controllers remain thin.
 */
final class SettingService
{
    private const string CACHE_KEY = 'central.settings.map';

    public function __construct(
        private readonly PaymentSettingsPolicy $paymentSettingsPolicy,
        private readonly TenantSettingsPolicy  $tenantSettingsPolicy,
        private readonly BillingSettingsPolicy $billingSettingsPolicy,
    )
    {
    }

    /**
     * List settings grouped by their setting group enum value.
     *
     * @param array{group?: string, search?: string, public?: bool} $filters
     * @return array<string, list<Setting>>
     */
    public function grouped(array $filters = []): array
    {
        return $this->list($filters)
            ->groupBy(fn(Setting $setting): string => $setting->group->value)
            ->all();
    }

    /**
     * List settings with optional group, search, and visibility filters.
     *
     * @param array{group?: string, search?: string, public?: bool} $filters
     * @return Collection<int, Setting>
     */
    public function list(array $filters = []): Collection
    {
        return Setting::query()
            ->when(
                $filters['group'] ?? null,
                fn($query, string $group) => $query->where('group', $group)
            )
            ->when(
                $filters['search'] ?? null,
                fn($query, string $search) => $query->where(function ($q) use ($search): void {
                    $q->where('key', 'like', "%{$search}%")
                        ->orWhere('label', 'like', "%{$search}%");
                })
            )
            ->when(
                array_key_exists('public', $filters) && $filters['public'] !== null,
                fn($query) => $query->where('is_public', (bool)$filters['public'])
            )
            ->orderBy('group')
            ->orderBy('sort_order')
            ->orderBy('key')
            ->get();
    }

    /**
     * Retrieve a single setting value by key from the cached map.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $map = $this->cachedMap();

        return $map[$key] ?? $default;
    }

    /**
     * Retrieve the full key-to-value map of all settings from cache.
     *
     * @return array<string, mixed>
     */
    public function cachedMap(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(30), function (): array {
            return Setting::query()
                ->get()
                ->mapWithKeys(fn(Setting $setting): array => [
                    $setting->key => $setting->resolvedValue(),
                ])
                ->all();
        });
    }

    /**
     * Retrieve a key-to-value map of public, non-encrypted settings.
     *
     * @param string|null $group
     * @return array<string, mixed>
     */
    public function publicMap(?string $group = null): array
    {
        return Setting::query()
            ->where('is_public', true)
            ->where('is_encrypted', false)
            ->when($group, fn($query, string $group) => $query->where('group', $group))
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn(Setting $setting): array => [
                $setting->key => $setting->resolvedValue(),
            ])
            ->all();
    }

    /**
     * Create a new platform setting.
     *
     * @param array<string, mixed> $data
     * @return Setting
     * @throws JsonException
     */
    public function create(array $data): Setting
    {
        $type = $data['type'] instanceof SettingType
            ? $data['type']
            : SettingType::from($data['type']);

        $group = $data['group'] instanceof SettingGroup
            ? $data['group']
            : SettingGroup::from($data['group']);

        $isEncrypted = (bool)($data['is_encrypted'] ?? false) || $type === SettingType::ENCRYPTED;

        $setting = Setting::query()->create([
            'group' => $group,
            'key' => $data['key'] ?? Str::slug($data['label'], '.'),
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
            'type' => $type,
            'value' => $this->serializeValue($data['value'] ?? null, $type, $isEncrypted),
            'default_value' => ['value' => $data['default_value'] ?? $data['value'] ?? null],
            'options' => $data['options'] ?? null,
            'is_public' => (bool)($data['is_public'] ?? false),
            'is_encrypted' => $isEncrypted,
            'is_readonly' => (bool)($data['is_readonly'] ?? false),
            'sort_order' => (int)($data['sort_order'] ?? 0),
        ]);

        $this->forgetCache();

        activity()
            ->performedOn($setting)
            ->withProperties(['key' => $setting->key])
            ->log('setting.created');

        return $setting;
    }

    /**
     * Serialize a setting value for storage based on type and encryption flag.
     *
     * @param mixed $value
     * @param SettingType $type
     * @param bool $encrypted
     * @return string|null
     * @throws JsonException
     */
    private function serializeValue(mixed $value, SettingType $type, bool $encrypted): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = match ($type) {
            SettingType::BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            SettingType::JSON, SettingType::ARRAY, SettingType::MULTI_SELECT => is_string($value)
                ? $value
                : json_encode($value, JSON_THROW_ON_ERROR),
            default => is_scalar($value) ? (string)$value : json_encode($value, JSON_THROW_ON_ERROR),
        };

        return $encrypted || $type === SettingType::ENCRYPTED
            ? encrypt($normalized)
            : $normalized;
    }

    /**
     * Invalidate the cached settings map.
     *
     * @return void
     */
    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);

        try {
            app(ApplySettingsToConfig::class)->apply();
        } catch (Throwable) {
            // Ignore during early boot or when the container is incomplete.
        }
    }

    /**
     * Update multiple settings by key within a single transaction.
     *
     * @param array<string, mixed> $values key => value
     * @return SupportCollection<int, Setting>
     * @throws Throwable
     */
    public function bulkUpdate(array $values): SupportCollection
    {
        $values = $this->paymentSettingsPolicy->normalizeUpdates(
            $values,
            $this->cachedMap(),
        );
        $values = $this->tenantSettingsPolicy->normalizeUpdates($values);
        $values = $this->billingSettingsPolicy->normalizeUpdates($values);

        return DB::transaction(function () use ($values): SupportCollection {
            $updated = collect();

            foreach ($values as $key => $value) {
                $setting = Setting::query()->where('key', $key)->firstOrFail();
                $updated->push($this->update($setting, ['value' => $value]));
            }

            return $updated->values();
        });
    }

    /**
     * Update an existing platform setting.
     *
     * @param Setting $setting
     * @param array<string, mixed> $data
     * @return Setting
     *
     * @throws ValidationException|JsonException
     */
    public function update(Setting $setting, array $data): Setting
    {
        if ($setting->is_readonly && array_key_exists('value', $data)) {
            throw ValidationException::withMessages([
                'value' => ['This setting is read-only.'],
            ]);
        }

        $type = isset($data['type'])
            ? SettingType::from($data['type'] instanceof SettingType ? $data['type']->value : $data['type'])
            : $setting->type;

        $isEncrypted = array_key_exists('is_encrypted', $data)
            ? (bool)$data['is_encrypted']
            : $setting->is_encrypted;

        if (array_key_exists('value', $data)) {
            $normalized = $this->billingSettingsPolicy->normalizeUpdates(
                [$setting->key => $data['value']],
                'value',
            );
            $data['value'] = $normalized[$setting->key];
            $data['value'] = $this->serializeValue($data['value'], $type, $isEncrypted);
        }

        $setting->fill(collect($data)->except(['key'])->all());
        $setting->type = $type;
        $setting->is_encrypted = $isEncrypted;
        $setting->save();

        $this->forgetCache();

        activity()
            ->performedOn($setting)
            ->withProperties(['key' => $setting->key])
            ->log('setting.updated');

        return $setting->refresh();
    }

    /**
     * Delete a platform setting.
     *
     * @param Setting $setting
     * @return void
     *
     * @throws ValidationException
     */
    public function delete(Setting $setting): void
    {
        if ($setting->is_readonly) {
            throw ValidationException::withMessages([
                'setting' => ['Read-only settings cannot be deleted.'],
            ]);
        }

        $setting->delete();
        $this->forgetCache();

        activity()
            ->withProperties(['key' => $setting->key])
            ->log('setting.deleted');
    }

    /**
     * List all available setting groups for UI selectors.
     *
     * @return list<array{value: string, label: string}>
     */
    public function groups(): array
    {
        return array_map(
            static fn(SettingGroup $group): array => [
                'value' => $group->value,
                'label' => $group->label(),
            ],
            SettingGroup::cases(),
        );
    }
}
