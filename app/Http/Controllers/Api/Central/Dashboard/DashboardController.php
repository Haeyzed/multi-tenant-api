<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Central\Dashboard\DashboardService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Central Dashboard', description: 'Stats, revenue, charts, health.', weight: 150)]
final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    #[Endpoint(operationId: 'dashboard.overview', title: 'dashboard overview', description: 'Return an overview payload for dashboards.')]
    public function overview(Request $request): JsonResponse
    {
        $this->authorize('viewDashboard');

        return $this->success(
            $this->dashboardService->overview(),
            'Dashboard overview retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'dashboard.statistics', title: 'dashboard statistics', description: 'Return aggregate statistics for dashboards.')]
    public function statistics(Request $request): JsonResponse
    {
        $this->authorize('viewDashboard');

        return $this->success(
            $this->dashboardService->statistics(),
            'Dashboard statistics retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'dashboard.revenue', title: 'Revenue metrics', description: 'Return MRR, ARR, and related revenue metrics.')]
    public function revenue(Request $request): JsonResponse
    {
        $this->authorize('viewDashboard');

        return $this->success(
            $this->dashboardService->revenue(),
            'Revenue metrics retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'dashboard.growth', title: 'Growth metrics', description: 'Compare current vs previous period growth.')]
    public function growth(Request $request): JsonResponse
    {
        $this->authorize('viewDashboard');

        $days = (int) $request->integer('days', 30);

        return $this->success(
            $this->dashboardService->growth($days),
            'Growth metrics retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'dashboard.charts', title: 'Charts data', description: 'Return time-series chart datasets.')]
    public function charts(Request $request): JsonResponse
    {
        $this->authorize('viewDashboard');

        $days = (int) $request->integer('days', 30);

        return $this->success(
            $this->dashboardService->charts($days),
            'Dashboard charts retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'dashboard.recentActivities', title: 'Recent activities', description: 'Return the latest platform activity entries.')]
    public function recentActivities(Request $request): JsonResponse
    {
        $this->authorize('viewDashboard');

        $limit = (int) $request->integer('limit', 15);

        return $this->success(
            $this->dashboardService->recentActivities($limit),
            'Recent activities retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'dashboard.notifications', title: 'Notification summary', description: 'Return unread/total notification counts for the user.')]
    public function notifications(Request $request): JsonResponse
    {
        $this->authorize('viewDashboard');

        return $this->success(
            $this->dashboardService->notificationsSummary($request->user()),
            'Notification summary retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'dashboard.health', title: 'dashboard health', description: 'Return health status details.')]
    public function health(Request $request): JsonResponse
    {
        $this->authorize('viewDashboardHealth');

        return $this->success(
            $this->dashboardService->platformHealth(),
            'Platform health retrieved successfully.',
        );
    }
}
