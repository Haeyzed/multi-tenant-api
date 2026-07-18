<?php

declare(strict_types=1);

namespace App\Services\Central\Audit;

use App\Models\Central\Tenant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Service responsible for central audit log queries and exports.
 *
 * Encapsulates activity log pagination, user/tenant scoped queries,
 * and CSV export so controllers remain thin.
 */
final class AuditService
{
    /**
     * Find a single activity log entry by ID with relationships loaded.
     *
     * @param int $id
     * @return Activity
     *
     * @throws ModelNotFoundException
     */
    public function find(int $id): Activity
    {
        return Activity::query()->with(['causer', 'subject'])->findOrFail($id);
    }

    /**
     * Activities caused by or performed on a user.
     *
     * @param User $user
     * @param array{search?: string, event?: string, from?: string, to?: string, per_page?: int} $filters
     * @return LengthAwarePaginator<int, Activity>
     */
    public function userActivities(User $user, array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int)($filters['per_page'] ?? 25), 100);

        return Activity::query()
            ->with(['causer', 'subject'])
            ->where(function ($query) use ($user): void {
                $query->where(function ($q) use ($user): void {
                    $q->where('causer_type', $user->getMorphClass())
                        ->where('causer_id', $user->getKey());
                })->orWhere(function ($q) use ($user): void {
                    $q->where('subject_type', $user->getMorphClass())
                        ->where('subject_id', $user->getKey());
                });
            })
            ->when(
                $filters['search'] ?? null,
                fn($query, string $search) => $query->where('description', 'like', "%{$search}%")
            )
            ->when(
                $filters['event'] ?? null,
                fn($query, string $event) => $query->where('event', $event)
            )
            ->when(
                $filters['from'] ?? null,
                fn($query, string $from) => $query->where('created_at', '>=', $from)
            )
            ->when(
                $filters['to'] ?? null,
                fn($query, string $to) => $query->where('created_at', '<=', $to)
            )
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Paginate activity log entries with optional filters.
     *
     * @param array{
     *     search?: string,
     *     log_name?: string,
     *     event?: string,
     *     causer_id?: int|string,
     *     subject_type?: string,
     *     subject_id?: int|string,
     *     from?: string,
     *     to?: string,
     *     per_page?: int
     * } $filters
     * @return LengthAwarePaginator<int, Activity>
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int)($filters['per_page'] ?? 25), 100);

        return $this->filteredQuery($filters)
            ->with(['causer', 'subject'])
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Build a filtered activity log query from the given filters.
     *
     * @param array{
     *     search?: string,
     *     log_name?: string,
     *     event?: string,
     *     causer_id?: int|string,
     *     subject_type?: string,
     *     subject_id?: int|string,
     *     from?: string,
     *     to?: string
     * } $filters
     * @return Builder<Activity>
     */
    private function filteredQuery(array $filters)
    {
        return Activity::query()
            ->when(
                $filters['search'] ?? null,
                fn($query, string $search) => $query->where(function ($q) use ($search): void {
                    $q->where('description', 'like', "%{$search}%")
                        ->orWhere('log_name', 'like', "%{$search}%")
                        ->orWhere('event', 'like', "%{$search}%");
                })
            )
            ->when(
                $filters['log_name'] ?? null,
                fn($query, string $logName) => $query->where('log_name', $logName)
            )
            ->when(
                $filters['event'] ?? null,
                fn($query, string $event) => $query->where('event', $event)
            )
            ->when(
                $filters['causer_id'] ?? null,
                fn($query, $causerId) => $query->where('causer_id', $causerId)
            )
            ->when(
                $filters['subject_type'] ?? null,
                fn($query, string $subjectType) => $query->where('subject_type', $subjectType)
            )
            ->when(
                $filters['subject_id'] ?? null,
                fn($query, $subjectId) => $query->where('subject_id', $subjectId)
            )
            ->when(
                $filters['from'] ?? null,
                fn($query, string $from) => $query->where('created_at', '>=', $from)
            )
            ->when(
                $filters['to'] ?? null,
                fn($query, string $to) => $query->where('created_at', '<=', $to)
            );
    }

    /**
     * Paginate activity log entries performed on a tenant.
     *
     * @param Tenant $tenant
     * @param array{search?: string, event?: string, from?: string, to?: string, per_page?: int} $filters
     * @return LengthAwarePaginator<int, Activity>
     */
    public function tenantActivities(Tenant $tenant, array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int)($filters['per_page'] ?? 25), 100);

        return Activity::query()
            ->with(['causer', 'subject'])
            ->where('subject_type', $tenant->getMorphClass())
            ->where('subject_id', $tenant->getKey())
            ->when(
                $filters['search'] ?? null,
                fn($query, string $search) => $query->where('description', 'like', "%{$search}%")
            )
            ->when(
                $filters['event'] ?? null,
                fn($query, string $event) => $query->where('event', $event)
            )
            ->when(
                $filters['from'] ?? null,
                fn($query, string $from) => $query->where('created_at', '>=', $from)
            )
            ->when(
                $filters['to'] ?? null,
                fn($query, string $to) => $query->where('created_at', '<=', $to)
            )
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Export filtered activity log entries as a CSV download.
     *
     * @param array{
     *     search?: string,
     *     log_name?: string,
     *     event?: string,
     *     causer_id?: int|string,
     *     subject_type?: string,
     *     subject_id?: int|string,
     *     from?: string,
     *     to?: string
     * } $filters
     * @return StreamedResponse
     */
    public function export(array $filters = []): StreamedResponse
    {
        $filename = 'audit-logs-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($filters): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'id', 'log_name', 'description', 'event', 'subject_type', 'subject_id',
                'causer_type', 'causer_id', 'properties', 'created_at',
            ]);

            $this->filteredQuery($filters)
                ->orderBy('id')
                ->chunk(500, function (Collection $activities) use ($handle): void {
                    foreach ($activities as $activity) {
                        /** @var Activity $activity */
                        fputcsv($handle, [
                            $activity->id,
                            $activity->log_name,
                            $activity->description,
                            $activity->event,
                            $activity->subject_type,
                            $activity->subject_id,
                            $activity->causer_type,
                            $activity->causer_id,
                            json_encode($activity->properties),
                            optional($activity->created_at)?->toIso8601String(),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
