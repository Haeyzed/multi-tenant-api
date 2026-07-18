<?php

declare(strict_types=1);

namespace App\Payments;

/**
 * Normalized payment method details returned after setup confirmation.
 */
final readonly class PaymentMethodPayload
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $gateway,
        public ?string $externalId = null,
        public ?string $customerExternalId = null,
        public ?string $authorizationCode = null,
        public ?string $brand = null,
        public ?string $lastFour = null,
        public ?int $expMonth = null,
        public ?int $expYear = null,
        public array $meta = [],
        public ?string $refundReference = null,
        public bool $shouldRefund = false,
        public float $chargedAmount = 0,
        public string $currency = 'USD',
    ) {}
}
