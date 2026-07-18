<?php

declare(strict_types=1);

namespace App\Services\Central\Monitoring;

use App\Enums\Central\QueueStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Service responsible for platform infrastructure monitoring.
 *
 * Aggregates database, cache, queue, storage, Redis, and server health
 * checks alongside failed job management for the monitoring dashboard.
 */
final class MonitoringService
{
    /**
     * Build the complete monitoring overview payload.
     *
     * @return array{status: string, checked_at: string, database: array<string, mixed>, cache: array<string, mixed>, queue: array<string, mixed>, storage: array<string, mixed>, redis: array<string, mixed>, server: array<string, mixed>, failed_jobs: array{count: int}}
     */
    public function overview(): array
    {
        return [
            'status' => $this->overallStatus(),
            'checked_at' => Carbon::now()->toIso8601String(),
            'database' => $this->database(),
            'cache' => $this->cache(),
            'queue' => $this->queue(),
            'storage' => $this->storage(),
            'redis' => $this->redis(),
            'server' => $this->server(),
            'failed_jobs' => [
                'count' => $this->failedJobsCount(),
            ],
        ];
    }

    /**
     * Derive overall platform health from core infrastructure checks.
     *
     * @return string  Either "healthy" or "degraded"
     */
    private function overallStatus(): string
    {
        $checks = [
            $this->database()['ok'] ?? false,
            $this->cache()['ok'] ?? false,
            $this->storage()['ok'] ?? false,
        ];

        return collect($checks)->every(fn($ok) => $ok === true) ? 'healthy' : 'degraded';
    }

    /**
     * Check database connectivity and measure query latency.
     *
     * @return array{ok: bool, driver?: mixed, latency_ms?: float, message?: string}
     */
    public function database(): array
    {
        try {
            $start = microtime(true);
            DB::select('select 1');
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'ok' => true,
                'driver' => config('database.default'),
                'latency_ms' => $latency,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Verify cache read/write functionality.
     *
     * @return array{ok: bool, driver?: mixed, message: string}
     */
    public function cache(): array
    {
        try {
            $key = 'monitoring.health.' . Str::random(8);
            Cache::put($key, true, 5);
            $ok = Cache::pull($key) === true;

            return [
                'ok' => $ok,
                'driver' => config('cache.default'),
                'message' => $ok ? 'Cache is writable.' : 'Cache write/read failed.',
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Check storage path writability and disk space.
     *
     * @return array{ok: bool, path: string, writable: bool, free_bytes: float|false|null, total_bytes: float|false|null}
     */
    public function storage(): array
    {
        $path = storage_path('app');
        $writable = is_dir($path) && is_writable($path);
        $free = @disk_free_space($path) ?: null;
        $total = @disk_total_space($path) ?: null;

        return [
            'ok' => $writable,
            'path' => $path,
            'writable' => $writable,
            'free_bytes' => $free,
            'total_bytes' => $total,
        ];
    }

    /**
     * Assess queue health based on pending and failed job counts.
     *
     * @return array{status: string, pending_jobs: int, failed_jobs: int, connection: mixed}
     */
    public function queue(): array
    {
        $failed = $this->failedJobsCount();
        $pending = Schema::hasTable('jobs')
            ? (int)DB::table('jobs')->count()
            : 0;

        $status = match (true) {
            $failed > 50 => QueueStatus::CRITICAL,
            $failed > 10 => QueueStatus::WARNING,
            default => QueueStatus::HEALTHY,
        };

        return [
            'status' => $status->value,
            'pending_jobs' => $pending,
            'failed_jobs' => $failed,
            'connection' => config('queue.default'),
        ];
    }

    /**
     * Count failed queue jobs when the table exists.
     *
     * @return int
     */
    private function failedJobsCount(): int
    {
        if (!Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return (int)DB::table('failed_jobs')->count();
    }

    /**
     * Check Redis connectivity when configured as cache or queue driver.
     *
     * @return array{ok: bool, configured: bool, message: string}
     */
    public function redis(): array
    {
        if (!extension_loaded('redis') && config('cache.default') !== 'redis' && config('queue.default') !== 'redis') {
            return [
                'ok' => true,
                'configured' => false,
                'message' => 'Redis is not the active cache/queue driver.',
            ];
        }

        try {
            if (config('cache.default') === 'redis') {
                Cache::store('redis')->put('monitoring.redis', true, 5);
                $ok = Cache::store('redis')->pull('monitoring.redis') === true;

                return ['ok' => $ok, 'configured' => true, 'message' => $ok ? 'Redis reachable.' : 'Redis check failed.'];
            }

            return ['ok' => true, 'configured' => false, 'message' => 'Redis driver not active.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'configured' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Collect server and runtime environment metadata.
     *
     * @return array{php_version: string, laravel_version: string, environment: mixed, debug: bool, timezone: mixed, memory_limit: string|false, memory_usage_bytes: int}
     */
    public function server(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => config('app.env'),
            'debug' => (bool)config('app.debug'),
            'timezone' => config('app.timezone'),
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage_bytes' => memory_get_usage(true),
        ];
    }

    /**
     * Paginate failed queue jobs for inspection.
     *
     * @param int $perPage
     * @param int $page
     * @return array{data: list<array{id: int, uuid: string, connection: string, queue: string, failed_at: string, exception: string}>, meta: array{total: int, per_page: int, current_page: int, last_page: int}}
     */
    public function failedJobs(int $perPage = 25, int $page = 1): array
    {
        if (!Schema::hasTable('failed_jobs')) {
            return ['data' => [], 'meta' => ['total' => 0, 'per_page' => $perPage, 'current_page' => $page]];
        }

        $perPage = min(max($perPage, 1), 100);
        $total = DB::table('failed_jobs')->count();
        $items = DB::table('failed_jobs')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get()
            ->map(static fn($job): array => [
                'id' => $job->id,
                'uuid' => $job->uuid,
                'connection' => $job->connection,
                'queue' => $job->queue,
                'failed_at' => $job->failed_at,
                'exception' => Str::limit((string)$job->exception, 500),
            ])
            ->all();

        return [
            'data' => $items,
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => max(1, (int)ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * Remove a failed job record by ID (simulated retry).
     *
     * @param int $id
     * @return bool  True when the job existed and was deleted
     */
    public function retryFailedJob(int $id): bool
    {
        if (!Schema::hasTable('failed_jobs')) {
            return false;
        }

        $job = DB::table('failed_jobs')->where('id', $id)->first();

        if ($job === null) {
            return false;
        }

        DB::table('failed_jobs')->where('id', $id)->delete();

        return true;
    }

    /**
     * Delete all failed job records.
     *
     * @return int  Number of deleted records
     */
    public function flushFailedJobs(): int
    {
        if (!Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return DB::table('failed_jobs')->delete();
    }
}
