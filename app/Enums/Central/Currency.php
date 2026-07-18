<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum Currency: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case CAD = 'CAD';
    case AUD = 'AUD';
    case JPY = 'JPY';
    case CNY = 'CNY';
    case INR = 'INR';
    case BRL = 'BRL';
    case MXN = 'MXN';
    case NGN = 'NGN';
    case ZAR = 'ZAR';
    case KES = 'KES';
    case GHS = 'GHS';
    case AED = 'AED';
    case SGD = 'SGD';
    case CHF = 'CHF';
    case SEK = 'SEK';
    case NOK = 'NOK';
    case DKK = 'DKK';
    case PLN = 'PLN';

    public static function toArray(): array
    {
        return array_reduce(
            self::cases(),
            static fn(array $carry, self $currency): array => [
                ...$carry,
                $currency->value => $currency->label(),
            ],
            []
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::USD => 'US Dollar',
            self::EUR => 'Euro',
            self::GBP => 'British Pound',
            self::CAD => 'Canadian Dollar',
            self::AUD => 'Australian Dollar',
            self::JPY => 'Japanese Yen',
            self::CNY => 'Chinese Yuan',
            self::INR => 'Indian Rupee',
            self::BRL => 'Brazilian Real',
            self::MXN => 'Mexican Peso',
            self::NGN => 'Nigerian Naira',
            self::ZAR => 'South African Rand',
            self::KES => 'Kenyan Shilling',
            self::GHS => 'Ghanaian Cedi',
            self::AED => 'UAE Dirham',
            self::SGD => 'Singapore Dollar',
            self::CHF => 'Swiss Franc',
            self::SEK => 'Swedish Krona',
            self::NOK => 'Norwegian Krone',
            self::DKK => 'Danish Krone',
            self::PLN => 'Polish Zloty',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::USD, self::CAD, self::AUD => '$',
            self::EUR => '€',
            self::GBP => '£',
            self::JPY, self::CNY => '¥',
            self::INR => '₹',
            self::BRL => 'R$',
            self::MXN => 'Mex$',
            self::NGN => '₦',
            self::ZAR => 'R',
            self::KES => 'KSh',
            self::GHS => 'GH₵',
            self::AED => 'د.إ',
            self::SGD => 'S$',
            self::CHF => 'Fr',
            self::SEK, self::NOK, self::DKK => 'kr',
            self::PLN => 'zł',
        };
    }

    public function decimals(): int
    {
        return match ($this) {
            self::JPY => 0,
            default => 2,
        };
    }
}
