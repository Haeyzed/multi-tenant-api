<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum PaymentGateway: string
{
    case STRIPE = 'stripe';
    case PAYSTACK = 'paystack';
    case FLUTTERWAVE = 'flutterwave';
    case LEMON_SQUEEZY = 'lemon_squeezy';
    case PADDLE = 'paddle';
    case PAYPAL = 'paypal';
    case RAZORPAY = 'razorpay';
    case BRAINTREE = 'braintree';
    case SQUARE = 'square';
    case MANUAL = 'manual';
    case BANK_TRANSFER = 'bank_transfer';
    case CRYPTO = 'crypto';

    public static function toArray(): array
    {
        return array_reduce(
            self::cases(),
            static fn(array $carry, self $gateway): array => [
                ...$carry,
                $gateway->value => $gateway->label(),
            ],
            []
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::STRIPE => 'Stripe',
            self::PAYSTACK => 'Paystack',
            self::FLUTTERWAVE => 'Flutterwave',
            self::LEMON_SQUEEZY => 'Lemon Squeezy',
            self::PADDLE => 'Paddle',
            self::PAYPAL => 'PayPal',
            self::RAZORPAY => 'Razorpay',
            self::BRAINTREE => 'Braintree',
            self::SQUARE => 'Square',
            self::MANUAL => 'Manual',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::CRYPTO => 'Cryptocurrency',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::STRIPE => 'credit-card',
            self::PAYSTACK => 'credit-card',
            self::FLUTTERWAVE => 'credit-card',
            self::LEMON_SQUEEZY => 'credit-card',
            self::PADDLE => 'credit-card',
            self::PAYPAL => 'paypal',
            self::RAZORPAY => 'credit-card',
            self::BRAINTREE => 'credit-card',
            self::SQUARE => 'credit-card',
            self::MANUAL => 'hand',
            self::BANK_TRANSFER => 'landmark',
            self::CRYPTO => 'bitcoin',
        };
    }

    public function supportsRecurring(): bool
    {
        return match ($this) {
            self::STRIPE, self::PAYSTACK, self::PADDLE, self::PAYPAL, self::RAZORPAY => true,
            default => false,
        };
    }

    public function supportsRefunds(): bool
    {
        return match ($this) {
            self::STRIPE, self::PAYSTACK, self::PAYPAL, self::RAZORPAY, self::SQUARE => true,
            default => false,
        };
    }
}
