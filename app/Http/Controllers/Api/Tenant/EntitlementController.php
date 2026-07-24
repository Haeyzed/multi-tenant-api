<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Services\Central\Billing\EntitlementService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Tenant Entitlements', description: 'Plan feature entitlements for the current tenant.', weight: 15)]
final class EntitlementController extends Controller
{
    public function __construct(
        private readonly EntitlementService $entitlements,
    ) {}

    #[Endpoint(operationId: 'tenant.entitlements.index', title: 'List entitlements', description: 'Return plan feature entitlements and usage for the current tenant.')]
    public function index(): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        return $this->success(
            $this->entitlements->summaryForTenant($tenant),
            'Entitlements retrieved successfully.',
        );
    }
}
