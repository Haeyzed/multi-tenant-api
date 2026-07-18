<?php

declare(strict_types=1);

namespace App\Services\Central\Tenants;

use Illuminate\Validation\ValidationException;

final class TenantSettingsPolicy
{
    /**
     * @var array<string, array{min: int, max: int}>
     */
    private const array INTEGER_RULES = [
        'tenant.default_trial_days' => ['min' => 0, 'max' => 365],
        'tenant.owner_invite_ttl_hours' => ['min' => 1, 'max' => 720],
        'tenant.signup_intent_ttl_hours' => ['min' => 1, 'max' => 168],
    ];

    /**
     * @var list<string>
     */
    private const array BOOLEAN_KEYS = [
        'tenant.auto_generate_domain',
        'tenant.default_force_https',
        'tenant.allow_custom_domains',
    ];

    /**
     * @param array<string, mixed> $updates
     * @return array<string, mixed>
     */
    public function normalizeUpdates(array $updates): array
    {
        foreach (self::INTEGER_RULES as $key => $bounds) {
            if (!array_key_exists($key, $updates)) {
                continue;
            }

            $value = filter_var($updates[$key], FILTER_VALIDATE_INT);

            if ($value === false || $value < $bounds['min'] || $value > $bounds['max']) {
                throw ValidationException::withMessages([
                    "settings.{$key}" => [
                        "The value must be an integer between {$bounds['min']} and {$bounds['max']}.",
                    ],
                ]);
            }

            $updates[$key] = $value;
        }

        foreach (self::BOOLEAN_KEYS as $key) {
            if (!array_key_exists($key, $updates)) {
                continue;
            }

            $value = filter_var($updates[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($value === null) {
                throw ValidationException::withMessages([
                    "settings.{$key}" => ['The value must be true or false.'],
                ]);
            }

            $updates[$key] = $value;
        }

        return $updates;
    }
}
