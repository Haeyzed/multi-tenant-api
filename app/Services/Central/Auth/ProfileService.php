<?php

declare(strict_types=1);

namespace App\Services\Central\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * Service responsible for central user profile management.
 *
 * Updates profile attributes, handles email verification state, and
 * generates signed verification URLs.
 */
final class ProfileService
{
    /**
     * Update the authenticated user's profile attributes.
     *
     * When the email changes, clears verification status and sends a new
     * verification notification.
     *
     * @param User $user
     * @param array{name?: string, email?: string, phone?: string|null, timezone?: string} $data
     * @return User
     */
    public function update(User $user, array $data): User
    {
        $emailChanged = isset($data['email']) && $data['email'] !== $user->email;

        $user->fill($data);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return $user->fresh(['roles', 'permissions']);
    }

    /**
     * Mark the user's email address as verified.
     *
     * No-op when the email is already verified. Dispatches the verified event
     * when verification succeeds.
     *
     * @param User $user
     */
    public function markEmailAsVerified(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        $user->markEmailAsVerified();
        event(new Verified($user));
    }

    /**
     * Resend the email verification notification.
     *
     * @param User $user
     *
     * @throws ValidationException
     */
    public function resendVerification(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['Email address is already verified.'],
            ]);
        }

        $user->sendEmailVerificationNotification();
    }

    /**
     * Generate a temporary signed URL for email verification.
     *
     * @param User $user
     * @return string
     */
    public function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'central.auth.verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );
    }
}
