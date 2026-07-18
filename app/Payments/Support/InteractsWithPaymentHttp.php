<?php

declare(strict_types=1);

namespace App\Payments\Support;

use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Shared helpers for live HTTP payment gateway drivers.
 */
trait InteractsWithPaymentHttp
{
    /**
     * Current payments mode from config (`test` or `live`).
     */
    protected function paymentsMode(): string
    {
        $mode = (string) config('payments.mode', 'test');

        return in_array($mode, ['test', 'live'], true) ? $mode : 'test';
    }

    /**
     * Whether live (production) provider credentials are in use.
     *
     * Both test and live modes call real provider HTTP APIs.
     */
    protected function isLiveMode(): bool
    {
        return $this->paymentsMode() === 'live';
    }

    /**
     * Whether provider HTTP APIs should be called (test or live).
     */
    protected function usesProviderApi(): bool
    {
        return in_array($this->paymentsMode(), ['test', 'live'], true);
    }

    /**
     * Convert a major-unit amount to the provider's smallest currency unit.
     */
    protected function toMinorUnits(float $amount, string $currency): int
    {
        $currency = Str::upper($currency);
        $zeroDecimal = config('payments.zero_decimal_currencies', []);

        if (in_array($currency, $zeroDecimal, true)) {
            return (int) round($amount);
        }

        return (int) round($amount * 100);
    }

    /**
     * Expand a configured redirect URL template with payment context.
     */
    protected function redirectUrl(string $key, Payment $payment): string
    {
        $template = (string) config("payments.{$key}", config('app.url'));

        return str_replace(
            ['{payment}', '{invoice}', '{tenant}'],
            [(string) $payment->id, (string) $payment->invoice_id, (string) $payment->tenant_id],
            $template,
        );
    }

    /**
     * Resolve the customer email for checkout sessions.
     */
    protected function customerEmail(Invoice $invoice): string
    {
        $invoice->loadMissing('tenant');

        return (string) ($invoice->tenant?->email ?: config('mail.from.address'));
    }

    /**
     * Build a configured HTTP client for provider API calls.
     *
     * @param  array<string, string>  $headers
     */
    protected function httpClient(string $baseUrl, array $headers = []): PendingRequest
    {
        return Http::baseUrl(rtrim($baseUrl, '/'))
            ->timeout(15)
            ->connectTimeout(5)
            ->retry([100, 300, 800], throw: false)
            ->acceptJson()
            ->withHeaders($headers);
    }
}
