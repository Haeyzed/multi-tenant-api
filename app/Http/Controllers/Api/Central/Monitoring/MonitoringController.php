<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Monitoring;

use App\Http\Controllers\Controller;
use App\Services\Central\Monitoring\MonitoringService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Central Monitoring', description: 'Queue, jobs, DB, storage, server health.', weight: 210)]
final class MonitoringController extends Controller
{
    public function __construct(
        private readonly MonitoringService $monitoringService,
    ) {}

    #[Endpoint(operationId: 'monitoring.overview', title: 'monitoring overview', description: 'Return an overview payload for monitorings.')]
    public function overview(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('monitoring.view'), 403);

        return $this->success($this->monitoringService->overview(), 'Monitoring overview retrieved successfully.');
    }

    #[Endpoint(operationId: 'monitoring.queue', title: 'Queue status', description: 'Return queue depth and failed-job health.')]
    public function queue(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('monitoring.view'), 403);

        return $this->success($this->monitoringService->queue(), 'Queue status retrieved successfully.');
    }

    #[Endpoint(operationId: 'monitoring.failedJobs', title: 'Failed jobs', description: 'Paginate failed queue jobs.')]
    public function failedJobs(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('monitoring.view'), 403);

        $result = $this->monitoringService->failedJobs(
            (int) $request->integer('per_page', 25),
            (int) $request->integer('page', 1),
        );

        return response()->json([
            'status' => true,
            'message' => 'Failed jobs retrieved successfully.',
            'data' => $result['data'],
            'meta' => (object) $result['meta'],
            'errors' => null,
        ]);
    }

    #[Endpoint(operationId: 'monitoring.retryFailedJob', title: 'Retry failed job', description: 'Delete a failed job record to allow re-queue workflows.')]
    public function retryFailedJob(Request $request, int $failedJob): JsonResponse
    {
        abort_unless($request->user()?->can('monitoring.manage'), 403);

        $ok = $this->monitoringService->retryFailedJob($failedJob);

        abort_unless($ok, 404);

        return $this->success(null, 'Failed job queued for retry.');
    }

    #[Endpoint(operationId: 'monitoring.flushFailedJobs', title: 'Flush failed jobs', description: 'Delete all failed job records.')]
    public function flushFailedJobs(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('monitoring.manage'), 403);

        $deleted = $this->monitoringService->flushFailedJobs();

        return $this->success(['deleted' => $deleted], 'Failed jobs flushed successfully.');
    }

    #[Endpoint(operationId: 'monitoring.database', title: 'Database health', description: 'Probe database connectivity and latency.')]
    public function database(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('monitoring.view'), 403);

        return $this->success($this->monitoringService->database(), 'Database health retrieved successfully.');
    }

    #[Endpoint(operationId: 'monitoring.storage', title: 'Storage health', description: 'Check storage path writability and disk space.')]
    public function storage(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('monitoring.view'), 403);

        return $this->success($this->monitoringService->storage(), 'Storage health retrieved successfully.');
    }

    #[Endpoint(operationId: 'monitoring.redis', title: 'Redis health', description: 'Probe Redis when configured as cache/queue driver.')]
    public function redis(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('monitoring.view'), 403);

        return $this->success($this->monitoringService->redis(), 'Redis health retrieved successfully.');
    }

    #[Endpoint(operationId: 'monitoring.server', title: 'Server info', description: 'Return PHP/Laravel runtime environment details.')]
    public function server(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('monitoring.view'), 403);

        return $this->success($this->monitoringService->server(), 'Server health retrieved successfully.');
    }
}

