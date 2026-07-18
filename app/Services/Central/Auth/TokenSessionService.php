<?php

declare(strict_types=1);

namespace App\Services\Central\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Service responsible for Sanctum token and session management.
 *
 * Lists active sessions, creates personal access tokens, and revokes
 * individual or bulk tokens for central users.
 */
final class TokenSessionService
{
    /**
     * List all Sanctum sessions for the given user.
     *
     * @param User $user
     * @return Collection<int, PersonalAccessToken>
     */
    public function listSessions(User $user): Collection
    {
        return $user->tokens()->latest()->get();
    }

    /**
     * Revoke all Sanctum sessions except the current access token.
     *
     * @param User $user
     * @return int  Number of tokens deleted
     */
    public function revokeOtherSessions(User $user): int
    {
        $currentId = $user->currentAccessToken()?->id;

        return $user->tokens()
            ->when($currentId, fn($query) => $query->where('id', '!=', $currentId))
            ->delete();
    }

    /**
     * Create a named personal access token with the given abilities.
     *
     * @param User $user
     * @param string $name
     * @param list<string> $abilities
     * @return array{token: PersonalAccessToken, plain_text_token: string}
     */
    public function createPersonalAccessToken(User $user, string $name, array $abilities = ['*']): array
    {
        $accessToken = $user->createToken($name, $abilities === [] ? ['*'] : $abilities);

        return [
            'token' => $accessToken->accessToken,
            'plain_text_token' => $accessToken->plainTextToken,
        ];
    }

    /**
     * List all personal access tokens for the given user.
     *
     * @param User $user
     * @return Collection<int, PersonalAccessToken>
     */
    public function listPersonalAccessTokens(User $user): Collection
    {
        return $user->tokens()->latest()->get();
    }

    /**
     * Revoke a specific personal access token by identifier.
     *
     * @param User $user
     * @param int $tokenId
     *
     * @throws ValidationException
     */
    public function revokePersonalAccessToken(User $user, int $tokenId): void
    {
        $this->revokeSession($user, $tokenId);
    }

    /**
     * Revoke a specific Sanctum session token by identifier.
     *
     * @param User $user
     * @param int $tokenId
     *
     * @throws ValidationException
     */
    public function revokeSession(User $user, int $tokenId): void
    {
        $deleted = $user->tokens()->whereKey($tokenId)->delete();

        if ($deleted === 0) {
            throw ValidationException::withMessages([
                'token' => ['The session token was not found.'],
            ]);
        }
    }
}
