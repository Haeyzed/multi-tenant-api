<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Central\PersonalAccessTokenResource;
use App\Models\User;
use App\Services\Central\Auth\TokenSessionService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Central Sessions', description: 'Active Sanctum sessions.', weight: 40)]
final class SessionController extends Controller
{
    public function __construct(
        private readonly TokenSessionService $tokenSessionService,
    )
    {
    }

    #[Endpoint(operationId: 'auth.session.index', title: 'List sessions', description: 'List active Sanctum sessions/tokens for the current user.')]
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $sessions = $this->tokenSessionService->listSessions($user);

        return $this->success(
            PersonalAccessTokenResource::collection($sessions),
            'Sessions retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'auth.session.destroy', title: 'Revoke session', description: 'Revoke a specific Sanctum session/token.')]
    public function destroy(Request $request, int $token): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->tokenSessionService->revokeSession($user, $token);

        return $this->success(null, 'Session revoked successfully.');
    }

    #[Endpoint(operationId: 'auth.session.destroyOthers', title: 'Revoke other sessions', description: 'Revoke all Sanctum tokens except the current one.')]
    public function destroyOthers(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $count = $this->tokenSessionService->revokeOtherSessions($user);

        return $this->success([
            'revoked' => $count,
        ], 'Other sessions revoked successfully.');
    }
}

