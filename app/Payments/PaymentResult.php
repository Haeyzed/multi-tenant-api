<?php

declare(strict_types=1);

namespace App\Payments;

/**
 * Immutable result returned by a payment gateway driver.
 *
 * Drivers return success, pending (customer action / webhook), or failure.
 */
final readonly class PaymentResult
{
    /**
     * @param  array<string, mixed>  $raw  Provider payload retained for auditing / debugging
     */
    public function __construct(
        public bool $successful,
        public string $reference,
        public string $status,
        public ?string $message = null,
        public array $raw = [],
    ) {}

    /**
     * Build a completed or accepted successful result.
     *
     * @param  array<string, mixed>  $raw
     */
    public static function success(string $reference, string $status = 'completed', array $raw = []): self
    {
        return new self(true, $reference, $status, null, $raw);
    }

    /**
     * Gateway accepted the request but payment awaits customer action or a webhook.
     *
     * @param  array<string, mixed>  $raw
     */
    public static function pending(string $reference, array $raw = [], ?string $message = null): self
    {
        return new self(true, $reference, 'pending', $message, $raw);
    }

    /**
     * Build a failed result with an optional provider reference.
     *
     * @param  array<string, mixed>  $raw
     */
    public static function failure(string $message, string $reference = '', array $raw = []): self
    {
        return new self(false, $reference, 'failed', $message, $raw);
    }

    /**
     * Whether the payment is awaiting completion.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Whether the payment completed successfully in this response.
     */
    public function isCompleted(): bool
    {
        return $this->successful && $this->status === 'completed';
    }

    /**
     * Checkout / authorization URL when the customer must finish payment off-site.
     */
    public function checkoutUrl(): ?string
    {
        $url = $this->raw['checkout_url'] ?? $this->raw['authorization_url'] ?? $this->raw['url'] ?? null;

        return is_string($url) ? $url : null;
    }
}
