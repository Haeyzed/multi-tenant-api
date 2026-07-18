<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Auth\CreatePersonalAccessTokenRequest;
use App\Http\Resources\Central\PersonalAccessTokenResource;
use App\Models\User;
use App\Services\Central\Auth\TokenSessionService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Central Tokens', description: 'Personal access tokens.', weight: 50)]
final class PersonalAccessTokenController extends Controller
{
    public function __construct(
        private readonly TokenSessionService $tokenSessionService,
    )
    {
    }

    #[Endpoint(operationId: 'auth.personalaccesstoken.index', title: 'List personal tokens', description: 'List personal access tokens for the current user.')]
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tokens = $this->tokenSessionService->listPersonalAccessTokens($user);

        return $this->success(
            PersonalAccessTokenResource::collection($tokens),
            'Personal access tokens retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'auth.personalaccesstoken.store', title: 'Create personal token', description: 'Create a personal access token and return the plaintext value once.')]
    public function store(CreatePersonalAccessTokenRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $this->tokenSessionService->createPersonalAccessToken(
            $user,
            $request->validated('name'),
            $request->validated('abilities', ['*'])
        );

        return $this->success([
            'token' => new PersonalAccessTokenResource($result['token']),
            'plain_text_token' => $result['plain_text_token'],
        ], 'Personal access token created successfully.', 201);
    }

    #[Endpoint(operationId: 'auth.personalaccesstoken.destroy', title: 'Revoke personal token', description: 'Revoke a personal access token.')]
    public function destroy(Request $request, int $token): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->tokenSessionService->revokePersonalAccessToken($user, $token);

        return $this->success(null, 'Personal access token revoked successfully.');
    }
}

