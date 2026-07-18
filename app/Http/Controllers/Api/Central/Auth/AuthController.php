<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Auth\ConfirmTwoFactorLoginRequest;
use App\Http\Requests\Central\Auth\ForgotPasswordRequest;
use App\Http\Requests\Central\Auth\LoginRequest;
use App\Http\Requests\Central\Auth\ResetPasswordRequest;
use App\Http\Resources\Central\UserResource;
use App\Models\User;
use App\Services\Central\Auth\AuthService;
use App\Services\Central\Auth\PasswordResetService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Central Auth', description: 'Login, logout, password reset.', weight: 10)]
final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService          $authService,
        private readonly PasswordResetService $passwordResetService,
    )
    {
    }

    #[Endpoint(operationId: 'auth.login', title: 'Login', description: 'Authenticate with email and password. May require 2FA confirmation.')]
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated(), $request->ip());

        if ($result['requires_two_factor']) {
            return $this->success([
                'requires_two_factor' => true,
                'two_factor_token' => $result['two_factor_token'],
                'user' => [
                    'email' => $result['user']->email,
                ],
            ], 'Two-factor authentication required.');
        }

        return $this->success([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'user' => new UserResource($result['user']),
            'requires_two_factor' => false,
        ], 'Logged in successfully.');
    }

    #[Endpoint(operationId: 'auth.confirmTwoFactor', title: 'Confirm 2FA login', description: 'Complete login after TOTP challenge.')]
    public function confirmTwoFactor(ConfirmTwoFactorLoginRequest $request): JsonResponse
    {
        $result = $this->authService->confirmTwoFactorLogin($request->validated(), $request->ip());

        return $this->success([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'user' => new UserResource($result['user']),
            'requires_two_factor' => false,
        ], 'Logged in successfully.');
    }

    #[Endpoint(operationId: 'auth.logout', title: 'Logout', description: 'Revoke the current Sanctum bearer token.')]
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authService->logout($user);

        return $this->success(null, 'Logged out successfully.');
    }

    #[Endpoint(operationId: 'auth.forgotPassword', title: 'Forgot password', description: 'Send a password reset link email.')]
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $message = $this->passwordResetService->sendResetLink($request->validated('email'));

        return $this->success(null, $message);
    }

    #[Endpoint(operationId: 'auth.resetPassword', title: 'Reset password', description: 'Reset password using a valid reset token.')]
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $message = $this->passwordResetService->reset($request->validated());

        return $this->success(null, $message);
    }
}

