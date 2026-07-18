<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Central\Auth\ProfileService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Central Auth', description: 'Login, logout, password reset.', weight: 10)]
final class EmailVerificationController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService,
    )
    {
    }

    #[Endpoint(operationId: 'auth.emailverification.verify', title: 'Verify email', description: 'Mark the user email as verified via signed link.')]
    public function verify(Request $request, int $id, string $hash): JsonResponse
    {
        if (!$request->hasValidSignature()) {
            return $this->error('Invalid or expired verification link.', 403);
        }

        /** @var User $user */
        $user = User::query()->findOrFail($id);

        if (!hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return $this->error('Invalid verification hash.', 403);
        }

        $this->profileService->markEmailAsVerified($user);

        return $this->success(null, 'Email verified successfully.');
    }

    #[Endpoint(operationId: 'auth.emailverification.resend', title: 'Resend verification', description: 'Resend the email verification notification.')]
    public function resend(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->profileService->resendVerification($user);

        return $this->success(null, 'Verification link sent.');
    }
}

