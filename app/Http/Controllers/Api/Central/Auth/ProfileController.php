<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Auth\ChangePasswordRequest;
use App\Http\Requests\Central\Auth\UpdateProfileRequest;
use App\Http\Resources\Central\UserResource;
use App\Models\User;
use App\Services\Central\Auth\PasswordResetService;
use App\Services\Central\Auth\ProfileService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Central Profile', description: 'Authenticated user profile and password.', weight: 20)]
final class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService       $profileService,
        private readonly PasswordResetService $passwordResetService,
    )
    {
    }

    #[Endpoint(operationId: 'auth.profile.show', title: 'Show profile', description: 'Return the authenticated user profile.')]
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->load(['roles', 'permissions']);

        return $this->success(new UserResource($user), 'Profile retrieved successfully.');
    }

    #[Endpoint(operationId: 'auth.profile.update', title: 'Update profile', description: 'Update the authenticated user profile.')]
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $updated = $this->profileService->update($user, $request->validated());

        return $this->success(new UserResource($updated), 'Profile updated successfully.');
    }

    #[Endpoint(operationId: 'auth.profile.changePassword', title: 'Change password', description: 'Update the authenticated user password.')]
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->passwordResetService->changePassword(
            $user,
            $request->validated('current_password'),
            $request->validated('password')
        );

        return $this->success(null, 'Password changed successfully.');
    }
}

