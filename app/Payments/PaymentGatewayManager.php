<?php

declare(strict_types=1);

namespace App\Payments;

use App\Enums\Central\PaymentGateway;
use App\Payments\Contracts\PaymentGatewayDriver;
use App\Payments\Drivers\FlutterwaveDriver;
use App\Payments\Drivers\LemonSqueezyDriver;
use App\Payments\Drivers\ManualDriver;
use App\Payments\Drivers\PaddleDriver;
use App\Payments\Drivers\PayPalDriver;
use App\Payments\Drivers\PaystackDriver;
use App\Payments\Drivers\StripeDriver;
use InvalidArgumentException;

/**
 * Resolves payment gateway drivers by name or PaymentGateway enum.
 */
final class PaymentGatewayManager
{
    /**
     * @var array<string, class-string<PaymentGatewayDriver>>
     */
    private array $drivers = [
        'stripe' => StripeDriver::class,
        'paystack' => PaystackDriver::class,
        'flutterwave' => FlutterwaveDriver::class,
        'paddle' => PaddleDriver::class,
        'lemon_squeezy' => LemonSqueezyDriver::class,
        'paypal' => PayPalDriver::class,
        'manual' => ManualDriver::class,
        'bank_transfer' => ManualDriver::class,
    ];

    /**
     * Resolve a driver instance for the given gateway.
     *
     * @throws InvalidArgumentException
     */
    public function driver(PaymentGateway|string $gateway): PaymentGatewayDriver
    {
        $key = $gateway instanceof PaymentGateway ? $gateway->value : $gateway;

        if (! isset($this->drivers[$key])) {
            throw new InvalidArgumentException("Unsupported payment gateway [{$key}].");
        }

        return app($this->drivers[$key]);
    }

    /**
     * List registered gateway keys.
     *
     * @return list<string>
     */
    public function available(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * Register or replace a driver binding at runtime.
     *
     * @param  class-string<PaymentGatewayDriver>  $driver
     */
    public function extend(string $name, string $driver): void
    {
        $this->drivers[$name] = $driver;
    }
}
