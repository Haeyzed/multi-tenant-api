<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Auth\ConfirmTwoFactorRequest;
use App\Http\Requests\Central\Auth\DisableTwoFactorRequest;
use App\Models\User;
use App\Services\Central\Auth\TwoFactorService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Central Two Factor', description: 'TOTP setup, confirm, disable, recovery codes.', weight: 30)]
final class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactorService,
    )
    {
    }

    #[Endpoint(operationId: 'auth.twofactor.enable', title: 'Enable 2FA', description: 'Begin TOTP two-factor setup and return a secret/QR payload.')]
    public function enable(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $setup = $this->twoFactorService->enable($user);

        return $this->success($setup, 'Two-factor setup started. Confirm with a valid code.');
    }

    #[Endpoint(operationId: 'auth.twofactor.confirm', title: 'Confirm 2FA setup', description: 'Confirm TOTP setup with a valid code.')]
    public function confirm(ConfirmTwoFactorRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->twoFactorService->confirm($user, $request->validated('code'));

        return $this->success(null, 'Two-factor authentication enabled.');
    }

    #[Endpoint(operationId: 'auth.twofactor.disable', title: 'Disable 2FA', description: 'Disable two-factor authentication for the user.')]
    public function disable(DisableTwoFactorRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->twoFactorService->disable($user, $request->validated('password'));

        return $this->success(null, 'Two-factor authentication disabled.');
    }

    #[Endpoint(operationId: 'auth.twofactor.recoveryCodes', title: '2FA recovery codes', description: 'Regenerate or retrieve two-factor recovery codes.')]
    public function recoveryCodes(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $codes = $this->twoFactorService->regenerateRecoveryCodes($user);

        return $this->success([
            'recovery_codes' => $codes,
        ], 'Recovery codes regenerated.');
    }
}

