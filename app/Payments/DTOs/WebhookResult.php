<?php

declare(strict_types=1);

namespace App\Payments\DTOs;

/**
 * Normalized webhook outcome returned by gateway drivers.
 */
final readonly class WebhookResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $handled,
        public string $status = 'ignored',
        public ?int $paymentId = null,
        public ?string $reference = null,
        public ?string $message = null,
        public bool $failed = false,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function ignored(array $raw = []): self
    {
        return new self(false, 'ignored', null, null, null, false, $raw);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function completed(?int $paymentId, string $reference, array $raw = []): self
    {
        return new self(true, 'completed', $paymentId, $reference, null, false, $raw);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function failed(?int $paymentId, string $message, array $raw = []): self
    {
        return new self(true, 'failed', $paymentId, null, $message, true, $raw);
    }
}
