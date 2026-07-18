<?php

declare(strict_types=1);

namespace App\Services\Central\Auth;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Service responsible for two-factor authentication lifecycle.
 *
 * Manages TOTP setup, confirmation, disablement, recovery codes, and
 * challenge validation during login.
 */
final class TwoFactorService
{
    /**
     * Begin two-factor authentication setup for a user.
     *
     * Generates a TOTP secret and recovery codes, persists hashed recovery
     * codes, and returns provisioning details for the authenticator app.
     *
     * @param User $user
     * @return array{secret: string, qr_code_url: string, recovery_codes: list<string>}
     *
     * @throws ValidationException
     */
    public function enable(User $user): array
    {
        if ($user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'two_factor' => ['Two-factor authentication is already enabled.'],
            ]);
        }

        $secret = Totp::generateSecret();
        $recovery = Totp::generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $recovery['hashed'],
            'two_factor_confirmed_at' => null,
        ])->save();

        return [
            'secret' => $secret,
            'qr_code_url' => Totp::provisioningUri(
                $secret,
                $user->email,
                (string)config('app.name', 'Central API')
            ),
            'recovery_codes' => $recovery['plain'],
        ];
    }

    /**
     * Confirm two-factor authentication with a valid TOTP code.
     *
     * Marks two-factor setup as complete after verifying the supplied code
     * against the pending secret.
     *
     * @param User $user
     * @param string $code
     *
     * @throws ValidationException
     */
    public function confirm(User $user, string $code): void
    {
        if (blank($user->two_factor_secret)) {
            throw ValidationException::withMessages([
                'code' => ['Two-factor setup has not been started.'],
            ]);
        }

        if ($user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'code' => ['Two-factor authentication is already confirmed.'],
            ]);
        }

        if (!Totp::verify($user->two_factor_secret, $code)) {
            throw ValidationException::withMessages([
                'code' => ['The provided two-factor code is invalid.'],
            ]);
        }

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();
    }

    /**
     * Disable two-factor authentication after password verification.
     *
     * Clears the TOTP secret, recovery codes, and confirmation timestamp.
     *
     * @param User $user
     * @param string $password
     *
     * @throws ValidationException
     */
    public function disable(User $user, string $password): void
    {
        if (!Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The password is incorrect.'],
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /**
     * Regenerate two-factor recovery codes for an enabled account.
     *
     * Replaces stored hashed recovery codes and returns the new plain-text set.
     *
     * @param User $user
     * @return list<string>
     *
     * @throws ValidationException
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        if (!$user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'two_factor' => ['Two-factor authentication is not enabled.'],
            ]);
        }

        $recovery = Totp::generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $recovery['hashed'],
        ])->save();

        return $recovery['plain'];
    }

    /**
     * Assert that a login challenge includes a valid TOTP or recovery code.
     *
     * Consumes a recovery code when one is supplied and persists the remaining
     * hashed codes on the user record.
     *
     * @param User $user
     * @param array{two_factor_code?: string, recovery_code?: string} $payload
     *
     * @throws ValidationException
     */
    public function assertValidChallenge(User $user, array $payload): void
    {
        if (!empty($payload['two_factor_code'])) {
            if (!Totp::verify((string)$user->two_factor_secret, $payload['two_factor_code'])) {
                throw ValidationException::withMessages([
                    'two_factor_code' => ['The provided two-factor code is invalid.'],
                ]);
            }

            return;
        }

        if (!empty($payload['recovery_code'])) {
            $remaining = Totp::consumeRecoveryCode(
                $user->two_factor_recovery_codes ?? [],
                $payload['recovery_code']
            );

            if ($remaining === null) {
                throw ValidationException::withMessages([
                    'recovery_code' => ['The provided recovery code is invalid.'],
                ]);
            }

            $user->forceFill([
                'two_factor_recovery_codes' => $remaining,
            ])->save();

            return;
        }

        throw ValidationException::withMessages([
            'two_factor_code' => ['A two-factor or recovery code is required.'],
        ]);
    }
}
