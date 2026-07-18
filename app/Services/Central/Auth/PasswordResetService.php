<?php

declare(strict_types=1);

namespace App\Services\Central\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Service responsible for password reset and change operations.
 *
 * Sends reset links, completes token-based password resets, and allows
 * authenticated users to change their password while revoking other sessions.
 */
final class PasswordResetService
{
    /**
     * Send a password reset link to the given email address.
     *
     * @param string $email
     * @return string  Localized status message on success
     *
     * @throws ValidationException
     */
    public function sendResetLink(string $email): string
    {
        $status = Password::sendResetLink(['email' => $email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return __($status);
    }

    /**
     * Reset a user's password using a valid reset token.
     *
     * Updates the password, rotates the remember token, revokes all Sanctum
     * tokens, and dispatches the password reset event.
     *
     * @param array{email: string, password: string, password_confirmation: string, token: string} $data
     * @return string  Localized status message on success
     *
     * @throws ValidationException
     */
    public function reset(array $data): string
    {
        $status = Password::reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return __($status);
    }

    /**
     * Change the authenticated user's password after verifying the current one.
     *
     * Revokes all Sanctum tokens except the current session token.
     *
     * @param User $user
     * @param string $currentPassword
     * @param string $newPassword
     *
     * @throws ValidationException
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => $newPassword]);
        $user->tokens()
            ->where('id', '!=', $user->currentAccessToken()?->id)
            ->delete();
    }
}
