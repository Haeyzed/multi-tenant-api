<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Setting;

/**
 * Tenant storefront/public branding settings.
 */
final class TenantSettingService
{
    public const KEY_STORE_NAME = 'store_name';

    public const KEY_BRAND_NAME = 'brand_name';

    public const KEY_BUSINESS_NAME = 'business_name';

    /**
     * @return array{brand_name: string|null, store_name: string|null, business_name: string|null}
     */
    public function publicMap(): array
    {
        $rows = Setting::query()
            ->whereIn('key', [
                self::KEY_STORE_NAME,
                self::KEY_BRAND_NAME,
                self::KEY_BUSINESS_NAME,
            ])
            ->pluck('value', 'key');

        $map = [
            'brand_name' => $this->stringOrNull($rows->get(self::KEY_BRAND_NAME)),
            'store_name' => $this->stringOrNull($rows->get(self::KEY_STORE_NAME)),
            'business_name' => $this->stringOrNull($rows->get(self::KEY_BUSINESS_NAME)),
        ];

        if ($map['store_name'] === null && $map['brand_name'] === null && $map['business_name'] === null) {
            $fallback = $this->stringOrNull(tenant('name'));
            if ($fallback !== null) {
                $map['store_name'] = $fallback;
                $map['brand_name'] = $fallback;
                $map['business_name'] = $fallback;
            }
        }

        return $map;
    }

    public function seedDefaults(string $storeName): void
    {
        $name = trim($storeName);
        if ($name === '') {
            $name = 'Store';
        }

        foreach ([
            self::KEY_STORE_NAME => $name,
            self::KEY_BRAND_NAME => $name,
            self::KEY_BUSINESS_NAME => $name,
        ] as $key => $value) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
