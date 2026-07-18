<?php

declare(strict_types=1);

namespace App\Payments;

/**
 * Result of starting a card setup / soft-verification session.
 */
final readonly class SetupSessionResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $successful,
        public string $reference,
        public ?string $checkoutUrl = null,
        public ?string $message = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function success(string $reference, string $checkoutUrl, array $raw = []): self
    {
        return new self(true, $reference, $checkoutUrl, null, $raw);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function failure(string $message, string $reference = '', array $raw = []): self
    {
        return new self(false, $reference, null, $message, $raw);
    }
}
