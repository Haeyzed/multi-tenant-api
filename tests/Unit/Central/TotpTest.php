<?php

declare(strict_types=1);

use App\Support\Totp;

it('generates and verifies totp codes', function (): void {
    $secret = Totp::generateSecret();
    $code = Totp::currentCode($secret);

    expect(Totp::verify($secret, $code))->toBeTrue()
        ->and(Totp::verify($secret, 'abcdef'))->toBeFalse();
});

it('builds a provisioning uri', function (): void {
    $uri = Totp::provisioningUri('JBSWY3DPEHPK3PXP', 'user@example.com', 'Central');

    expect($uri)->toStartWith('otpauth://totp/')
        ->and($uri)->toContain('secret=JBSWY3DPEHPK3PXP');
});

it('consumes recovery codes', function (): void {
    $recovery = Totp::generateRecoveryCodes(2);
    $remaining = Totp::consumeRecoveryCode($recovery['hashed'], $recovery['plain'][0]);

    expect($remaining)->toHaveCount(1)
        ->and(Totp::consumeRecoveryCode($remaining, 'NOPE-CODE'))->toBeNull();
});
