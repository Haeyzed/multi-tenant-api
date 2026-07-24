<?php

declare(strict_types=1);

namespace App\Services\Central\Billing;

use App\Models\Central\ExchangeRate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Reporting-only FX helpers. Must not feed subscription or plan price resolution.
 */
final class ExchangeRateService
{
    /**
     * Return the most recently observed conversion rate, if one exists.
     */
    public function getRate(string $baseCurrency, string $quoteCurrency): ?float
    {
        if (! Schema::hasTable('exchange_rates')) {
            return null;
        }

        $base = Str::upper(trim($baseCurrency));
        $quote = Str::upper(trim($quoteCurrency));

        if ($base === $quote) {
            return 1.0;
        }

        $rate = ExchangeRate::query()
            ->where('base_currency', $base)
            ->where('quote_currency', $quote)
            ->latest('observed_at')
            ->value('rate');

        return $rate !== null ? (float) $rate : null;
    }

    /**
     * Convert an amount using the latest stored reporting exchange rate.
     */
    public function convert(float $amount, string $fromCurrency, string $toCurrency): ?float
    {
        $rate = $this->getRate($fromCurrency, $toCurrency);

        return $rate === null ? null : round($amount * $rate, 2);
    }
}
