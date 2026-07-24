<?php

declare(strict_types=1);

namespace App\Facades;

use App\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Payments\Contracts\PaymentGatewayDriver driver(\App\Enums\Central\PaymentGateway|string $gateway)
 * @method static list<string> available()
 * @method static void extend(string $name, class-string<\App\Payments\Contracts\PaymentGatewayDriver> $driver)
 *
 * @see PaymentGatewayManager
 */
final class Payment extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PaymentGatewayManager::class;
    }
}
