<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Enums\Central\PlanStatus;
use App\Enums\Central\SubscriptionInterval;
use App\Models\Central\BillingProfile;
use App\Models\Central\Plan;
use App\Models\Central\PlanPrice;
use App\Services\Central\Settings\SettingService;
use App\Services\Central\World\WorldService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Resolves the best PlanPrice for a plan given billing country / currency.
 */
final class PlanPriceResolver
{
    public function __construct(
        private readonly WorldService $world,
        private readonly SettingService $settings,
        private readonly BillingSettings $billingSettings,
    ) {}

    /**
     * Resolve an active price for a plan from country (preferred) or currency.
     *
     * @throws ValidationException
     */
    public function resolve(
        Plan $plan,
        ?string $countryIso2 = null,
        ?string $currency = null,
        SubscriptionInterval|string|null $interval = null,
        ?BillingProfile $billingProfile = null,
    ): PlanPrice {
        if ($plan->status !== PlanStatus::Active) {
            throw ValidationException::withMessages([
                'plan_id' => ['The selected plan is not active.'],
            ]);
        }

        $billingInterval = $interval instanceof SubscriptionInterval
            ? $interval
            : (SubscriptionInterval::tryFrom((string) ($interval ?? '')) ?? SubscriptionInterval::MONTHLY);

        $preferredCurrency = filled($billingProfile?->currency)
            ? Str::upper((string) $billingProfile->currency)
            : (filled($currency)
            ? Str::upper(trim((string) $currency))
            : null);

        if ($preferredCurrency === null && filled($countryIso2)) {
            $preferredCurrency = $this->world->currencyForCountry((string) $countryIso2);
        }

        if (filled($preferredCurrency)) {
            $match = $this->findActivePrice($plan, $preferredCurrency, $billingInterval);

            if ($match !== null) {
                return $match;
            }
        }

        $fallbackCurrency = Str::upper((string) $this->settings->get(
            'billing.default_currency',
            config('payments.currency', 'USD'),
        ));
        $fallbackMode = $this->billingSettings->priceFallbackMode();

        $fallback = null;

        if ($preferredCurrency === null || $fallbackMode !== BillingSettingsPolicy::FALLBACK_EXACT_CURRENCY) {
            $fallback = $this->findActivePrice($plan, $fallbackCurrency, $billingInterval);
        }

        if (
            $fallback === null
            && in_array($fallbackMode, [
                BillingSettingsPolicy::FALLBACK_DEFAULT_CURRENCY,
                BillingSettingsPolicy::FALLBACK_ANY_ACTIVE,
            ], true)
        ) {
            $fallback = $this->findActivePrice($plan, $fallbackCurrency, null);
        }

        if (
            $fallback === null
            && in_array($fallbackMode, [
                BillingSettingsPolicy::FALLBACK_SAME_INTERVAL,
                BillingSettingsPolicy::FALLBACK_ANY_ACTIVE,
            ], true)
        ) {
            $fallback = $plan->prices()
                ->where('status', PlanStatus::Active)
                ->where('billing_interval', $billingInterval)
                ->orderBy('id')
                ->first();
        }

        if ($fallback === null && $fallbackMode === BillingSettingsPolicy::FALLBACK_ANY_ACTIVE) {
            $fallback = $plan->prices()
                ->where('status', PlanStatus::Active)
                ->orderBy('id')
                ->first();
        }

        if ($fallback === null) {
            throw ValidationException::withMessages([
                'plan_id' => ['No active price is configured for this plan.'],
            ]);
        }

        return $fallback;
    }

    private function findActivePrice(
        Plan $plan,
        string $currency,
        ?SubscriptionInterval $interval,
    ): ?PlanPrice {
        return $plan->prices()
            ->where('status', PlanStatus::Active)
            ->where('currency', Str::upper($currency))
            ->when(
                $interval !== null,
                fn ($query) => $query->where('billing_interval', $interval),
            )
            ->orderBy('id')
            ->first();
    }
}
