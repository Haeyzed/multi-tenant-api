<?php

declare(strict_types=1);

namespace App\Services\Central\Auth;

use App\Enums\Central\UserStatus;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use Throwable;

/**
 * Service responsible for central user authentication.
 *
 * Handles credential validation, session token issuance, two-factor login
 * challenges, logout, and self-service registration.
 */
final class AuthService
{
    public function __construct(
        private readonly TwoFactorService $twoFactorService,
    )
    {
    }

    /**
     * Authenticate a user with email and password credentials.
     *
     * Validates credentials and account status. When two-factor authentication
     * is enabled, returns a challenge token unless a valid TOTP or recovery
     * code is supplied in the credentials payload.
     *
     * @param array{email: string, password: string, device_name?: string, two_factor_code?: string, recovery_code?: string} $credentials
     * @param string|null $ip
     * @return array{user: User, token: string|null, requires_two_factor: bool, two_factor_token: string|null}
     *
     * @throws ValidationException
     */
    public function login(array $credentials, ?string $ip = null): array
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (!$user->canAuthenticate()) {
            throw ValidationException::withMessages([
                'email' => ['Your account is not active.'],
            ]);
        }

        if ($user->hasTwoFactorEnabled()) {
            if (!empty($credentials['two_factor_code']) || !empty($credentials['recovery_code'])) {
                $this->twoFactorService->assertValidChallenge($user, $credentials);

                return $this->issueAuthenticatedSession($user, $credentials['device_name'] ?? 'api', $ip);
            }

            return [
                'user' => $user,
                'token' => null,
                'requires_two_factor' => true,
                'two_factor_token' => $this->createTwoFactorChallenge($user),
            ];
        }

        return $this->issueAuthenticatedSession($user, $credentials['device_name'] ?? 'api', $ip);
    }

    /**
     * Issue an authenticated Sanctum session for the given user.
     *
     * Persists last-login metadata, creates an API token, and returns the
     * user with roles and permissions loaded.
     *
     * @param User $user
     * @param string $deviceName
     * @param string|null $ip
     * @return array{user: User, token: string, requires_two_factor: bool, two_factor_token: null}
     */
    private function issueAuthenticatedSession(User $user, string $deviceName, ?string $ip): array
    {
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ])->save();

        /** @var NewAccessToken $accessToken */
        $accessToken = $user->createToken($deviceName);

        return [
            'user' => $user->fresh(['roles', 'permissions']),
            'token' => $accessToken->plainTextToken,
            'requires_two_factor' => false,
            'two_factor_token' => null,
        ];
    }

    /**
     * Create a short-lived cache token for a pending two-factor login challenge.
     *
     * @param User $user
     * @return string
     */
    private function createTwoFactorChallenge(User $user): string
    {
        $token = Str::random(64);

        Cache::put($this->twoFactorChallengeKey($token), $user->id, now()->addMinutes(10));

        return $token;
    }

    private function twoFactorChallengeKey(string $token): string
    {
        return 'central.auth.2fa.' . $token;
    }

    /**
     * Complete login after a two-factor challenge token.
     *
     * Resolves the pending challenge, validates the supplied TOTP or recovery
     * code, invalidates the challenge token, and issues an authenticated session.
     *
     * @param array{two_factor_token: string, two_factor_code?: string, recovery_code?: string, device_name?: string} $payload
     * @param string|null $ip
     * @return array{user: User, token: string, requires_two_factor: bool, two_factor_token: null}
     *
     * @throws ValidationException
     */
    public function confirmTwoFactorLogin(array $payload, ?string $ip = null): array
    {
        $user = $this->resolveTwoFactorChallenge($payload['two_factor_token']);
        $this->twoFactorService->assertValidChallenge($user, $payload);

        Cache::forget($this->twoFactorChallengeKey($payload['two_factor_token']));

        return $this->issueAuthenticatedSession($user, $payload['device_name'] ?? 'api', $ip);
    }

    /**
     * Resolve a pending two-factor login challenge to its user.
     *
     * @param string $token
     * @return User
     *
     * @throws ValidationException
     */
    private function resolveTwoFactorChallenge(string $token): User
    {
        $userId = Cache::get($this->twoFactorChallengeKey($token));

        if (!$userId) {
            throw ValidationException::withMessages([
                'two_factor_token' => ['The two-factor challenge has expired or is invalid.'],
            ]);
        }

        /** @var User $user */
        $user = User::query()->findOrFail($userId);

        return $user;
    }

    /**
     * Revoke the current Sanctum access token for the authenticated user.
     *
     * @param User $user
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    /**
     * Register a new central user account.
     *
     * Creates the user with an active status and dispatches the registered event.
     *
     * @param array{name: string, email: string, password: string} $data
     * @return User
     * @throws Throwable
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'status' => UserStatus::Active,
            ]);

            event(new Registered($user));

            return $user;
        });
    }
}
