<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Auth\RedeemImpersonationRequest;
use App\Http\Requests\Tenant\Auth\SetupPasswordRequest;
use App\Http\Requests\Tenant\Auth\TenantLoginRequest;
use App\Http\Resources\Tenant\TenantUserResource;
use App\Models\Tenant\User as TenantUser;
use App\Services\Tenant\Auth\TenantAuthService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Tenant Auth', description: 'Tenant-domain owner authentication.', weight: 5)]
final class AuthController extends Controller
{
    public function __construct(
        private readonly TenantAuthService $authService,
    ) {}

    #[Endpoint(operationId: 'tenant.auth.setupPassword', title: 'Setup owner password', description: 'Accept an invite token and set the owner password.')]
    public function setupPassword(SetupPasswordRequest $request): JsonResponse
    {
        $result = $this->authService->setupPassword(
            $request->validated('token'),
            $request->validated('password'),
        );

        return $this->success([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'user' => new TenantUserResource($result['user']),
        ], 'Password set successfully.', 201);
    }

    #[Endpoint(operationId: 'tenant.auth.login', title: 'Tenant login', description: 'Authenticate a tenant user with email and password.')]
    public function login(TenantLoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->ip(),
        );

        return $this->success([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'user' => new TenantUserResource($result['user']),
        ], 'Logged in successfully.');
    }

    #[Endpoint(operationId: 'tenant.auth.impersonate', title: 'Redeem impersonation', description: 'Exchange a Central impersonation token for a tenant Sanctum token.')]
    public function impersonate(RedeemImpersonationRequest $request): JsonResponse
    {
        $result = $this->authService->redeemImpersonation($request->validated('token'));

        return $this->success([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'user' => new TenantUserResource($result['user']),
            'impersonating' => true,
        ], 'Impersonation session started.');
    }

    #[Endpoint(operationId: 'tenant.auth.me', title: 'Current tenant user', description: 'Return the authenticated tenant user.')]
    public function me(Request $request): JsonResponse
    {
        /** @var TenantUser $user */
        $user = $request->user();

        return $this->success(new TenantUserResource($user), 'Profile retrieved successfully.');
    }

    #[Endpoint(operationId: 'tenant.auth.logout', title: 'Tenant logout', description: 'Revoke the current tenant Sanctum token.')]
    public function logout(Request $request): JsonResponse
    {
        /** @var TenantUser $user */
        $user = $request->user();
        $this->authService->logout($user);

        return $this->success(null, 'Logged out successfully.');
    }
}
