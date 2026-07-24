<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\TenantSettingService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Tenant Settings', description: 'Public tenant branding settings.', weight: 12)]
final class SettingController extends Controller
{
    public function __construct(
        private readonly TenantSettingService $settings,
    ) {}

    #[Endpoint(operationId: 'tenant.settings.public', title: 'Public settings', description: 'Return public store branding settings for the current tenant.')]
    public function publicSettings(): JsonResponse
    {
        return $this->success(
            $this->settings->publicMap(),
            'Public settings retrieved successfully.',
        );
    }
}
