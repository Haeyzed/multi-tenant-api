<?php

declare(strict_types=1);

namespace App\Services\Central\Dashboard;

use App\Enums\Central\PaymentStatus;
use App\Enums\Central\SubscriptionInterval;
use App\Enums\Central\SubscriptionStatus;
use App\Enums\Central\TenantStatus;
use App\Models\Central\Payment;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use stdClass;
use Throwable;

/**
 * Service responsible for central dashboard analytics and platform health.
 *
 * Aggregates tenant, subscription, payment, and user statistics alongside
 * revenue metrics, growth trends, charts, and infrastructure health checks.
 */
final class DashboardService
{
    /**
     * Build the complete dashboard overview payload.
     *
     * @return array{statistics: array<string, mixed>, revenue: array{mrr: float, arr: float, currency: string, recurring_subscriptions: int}, growth: array<string, mixed>, platform_health: array<string, mixed>}
     */
    public function overview(): array
    {
        return [
            'statistics' => $this->statistics(),
            'revenue' => $this->revenue(),
            'growth' => $this->growth(),
            'platform_health' => $this->platformHealth(),
        ];
    }

    /**
     * Aggregate platform-wide entity counts and status breakdowns.
     *
     * @return array{tenants: array{total: int, active: int, trial: int, suspended: int, by_status: array<string, int|string>}, subscriptions: array{total: int, active: int, trialing: int, past_due: int, by_status: array<string, int|string>}, payments: array{total: int, completed: int, failed: int, volume: float}, users: array{total: int}}
     */
    public function statistics(): array
    {
        $tenantsByStatus = Tenant::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $subscriptionsByStatus = Subscription::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return [
            'tenants' => [
                'total' => Tenant::query()->count(),
                'active' => (int)($tenantsByStatus[TenantStatus::ACTIVE->value] ?? 0),
                'trial' => (int)($tenantsByStatus[TenantStatus::TRIAL->value] ?? 0),
                'suspended' => (int)($tenantsByStatus[TenantStatus::SUSPENDED->value] ?? 0),
                'by_status' => $tenantsByStatus,
            ],
            'subscriptions' => [
                'total' => Subscription::query()->count(),
                'active' => (int)($subscriptionsByStatus[SubscriptionStatus::ACTIVE->value] ?? 0),
                'trialing' => (int)($subscriptionsByStatus[SubscriptionStatus::TRIALING->value] ?? 0),
                'past_due' => (int)($subscriptionsByStatus[SubscriptionStatus::PAST_DUE->value] ?? 0),
                'by_status' => $subscriptionsByStatus,
            ],
            'payments' => [
                'total' => Payment::query()->count(),
                'completed' => Payment::query()->where('status', PaymentStatus::COMPLETED)->count(),
                'failed' => Payment::query()->where('status', PaymentStatus::FAILED)->count(),
                'volume' => (float)Payment::query()
                    ->where('status', PaymentStatus::COMPLETED)
                    ->sum('amount'),
            ],
            'users' => [
                'total' => User::query()->count(),
            ],
        ];
    }

    /**
     * Calculate monthly and annual recurring revenue from active subscriptions.
     *
     * @return array{mrr: float, arr: float, currency: string, recurring_subscriptions: int}
     */
    public function revenue(): array
    {
        $subscriptions = Subscription::query()
            ->whereIn('status', [
                SubscriptionStatus::ACTIVE->value,
                SubscriptionStatus::TRIALING->value,
                SubscriptionStatus::PAST_DUE->value,
            ])
            ->get(['price', 'billing_interval', 'currency']);

        $mrr = $subscriptions->sum(function (Subscription $subscription): float {
            $price = (float)$subscription->price;

            return match ($subscription->billing_interval) {
                SubscriptionInterval::MONTHLY => $price,
                SubscriptionInterval::QUARTERLY => $price / 3,
                SubscriptionInterval::YEARLY => $price / 12,
                default => 0.0,
            };
        });

        return [
            'mrr' => round($mrr, 2),
            'arr' => round($mrr * 12, 2),
            'currency' => $subscriptions->first()?->currency ?? 'USD',
            'recurring_subscriptions' => $subscriptions->count(),
        ];
    }

    /**
     * Compare tenant, subscription, and revenue growth over a rolling period.
     *
     * @param int $days Number of days in the current comparison window (1–365)
     * @return array{period_days: int, tenants: array{current: int, previous: int, change_percent: float}, subscriptions: array{current: int, previous: int, change_percent: float}, revenue: array{current: float, previous: float, change_percent: float}}
     */
    public function growth(int $days = 30): array
    {
        $days = max(1, min($days, 365));
        $currentStart = now()->subDays($days)->startOfDay();
        $previousStart = now()->subDays($days * 2)->startOfDay();
        $previousEnd = $currentStart->copy()->subSecond();

        $tenantsCurrent = Tenant::query()->where('created_at', '>=', $currentStart)->count();
        $tenantsPrevious = Tenant::query()
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();

        $revenueCurrent = (float)Payment::query()
            ->where('status', PaymentStatus::COMPLETED)
            ->where('paid_at', '>=', $currentStart)
            ->sum('amount');

        $revenuePrevious = (float)Payment::query()
            ->where('status', PaymentStatus::COMPLETED)
            ->whereBetween('paid_at', [$previousStart, $previousEnd])
            ->sum('amount');

        $subscriptionsCurrent = Subscription::query()->where('created_at', '>=', $currentStart)->count();
        $subscriptionsPrevious = Subscription::query()
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();

        return [
            'period_days' => $days,
            'tenants' => [
                'current' => $tenantsCurrent,
                'previous' => $tenantsPrevious,
                'change_percent' => $this->percentChange($tenantsPrevious, $tenantsCurrent),
            ],
            'subscriptions' => [
                'current' => $subscriptionsCurrent,
                'previous' => $subscriptionsPrevious,
                'change_percent' => $this->percentChange($subscriptionsPrevious, $subscriptionsCurrent),
            ],
            'revenue' => [
                'current' => round($revenueCurrent, 2),
                'previous' => round($revenuePrevious, 2),
                'change_percent' => $this->percentChange($revenuePrevious, $revenueCurrent),
            ],
        ];
    }

    /**
     * Calculate percentage change between two numeric values.
     *
     * Returns 100 when previous is zero and current is positive; 0 otherwise.
     *
     * @param float|int $previous
     * @param float|int $current
     * @return float
     */
    private function percentChange(float|int $previous, float|int $current): float
    {
        if ((float)$previous === 0.0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * Run infrastructure health checks and derive an overall status.
     *
     * @return array{status: string, checked_at: string, checks: array{database: array{ok: bool, message: string}, cache: array{ok: bool, message: string}, storage: array{ok: bool, message: string}}}
     */
    public function platformHealth(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
        ];

        $healthy = collect($checks)->every(fn(array $check): bool => $check['ok']);

        return [
            'status' => $healthy ? 'healthy' : 'degraded',
            'checked_at' => Carbon::now()->toIso8601String(),
            'checks' => $checks,
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');

            return ['ok' => true, 'message' => 'Database connection is healthy.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function checkCache(): array
    {
        try {
            $key = 'dashboard.health.' . Str::random(8);
            Cache::put($key, true, 5);
            $ok = Cache::pull($key) === true;

            return [
                'ok' => $ok,
                'message' => $ok ? 'Cache is writable.' : 'Cache write/read failed.',
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function checkStorage(): array
    {
        try {
            $path = storage_path('app');
            $writable = is_dir($path) && is_writable($path);

            return [
                'ok' => $writable,
                'message' => $writable ? 'Storage path is writable.' : 'Storage path is not writable.',
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Build daily time-series chart data for revenue, tenants, and subscriptions.
     *
     * @param int $days Number of days to include (1–365)
     * @return array{revenue: list<array{date: string, amount: float}>, tenants: list<array{date: string, count: int}>, subscriptions: list<array{date: string, count: int}>}
     */
    public function charts(int $days = 30): array
    {
        $days = max(1, min($days, 365));
        $start = now()->subDays($days - 1)->startOfDay();

        $revenue = Payment::query()
            ->selectRaw('DATE(paid_at) as day, SUM(amount) as total')
            ->where('status', PaymentStatus::COMPLETED)
            ->where('paid_at', '>=', $start)
            ->groupBy('day')
            ->pluck('total', 'day');

        $tenants = Tenant::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', $start)
            ->groupBy('day')
            ->pluck('total', 'day');

        $subscriptions = Subscription::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', $start)
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();
            $series[] = $date;
        }

        return [
            'revenue' => array_map(
                static fn(string $date): array => [
                    'date' => $date,
                    'amount' => round((float)($revenue[$date] ?? 0), 2),
                ],
                $series,
            ),
            'tenants' => array_map(
                static fn(string $date): array => [
                    'date' => $date,
                    'count' => (int)($tenants[$date] ?? 0),
                ],
                $series,
            ),
            'subscriptions' => array_map(
                static fn(string $date): array => [
                    'date' => $date,
                    'count' => (int)($subscriptions[$date] ?? 0),
                ],
                $series,
            ),
        ];
    }

    /**
     * Retrieve the most recent activity log entries for the dashboard feed.
     *
     * @param int $limit Maximum entries to return (1–50)
     * @return list<array{id: int|string, log_name: string|null, description: string, event: string|null, subject_type: string|null, subject_id: int|string|null, causer_type: string|null, causer_id: int|string|null, created_at: Carbon|null}>
     */
    public function recentActivities(int $limit = 15): array
    {
        $limit = max(1, min($limit, 50));

        return Activity::query()
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(static fn(Activity $activity): array => [
                'id' => $activity->id,
                'log_name' => $activity->log_name,
                'description' => $activity->description,
                'event' => $activity->event,
                'subject_type' => $activity->subject_type,
                'subject_id' => $activity->subject_id,
                'causer_type' => $activity->causer_type,
                'causer_id' => $activity->causer_id,
                'created_at' => $activity->created_at,
            ])
            ->all();
    }

    /**
     * Summarize unread and recent notifications for a dashboard user.
     *
     * Returns empty counts when the notifications table does not exist.
     *
     * @param User $user
     * @return array{unread: int, total: int, recent: list<stdClass>}
     */
    public function notificationsSummary(User $user): array
    {
        if (!Schema::hasTable('notifications')) {
            return [
                'unread' => 0,
                'total' => 0,
                'recent' => [],
            ];
        }

        $query = DB::table('notifications')->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey());

        return [
            'unread' => (clone $query)->whereNull('read_at')->count(),
            'total' => (clone $query)->count(),
            'recent' => (clone $query)->latest('created_at')->limit(5)->get()->all(),
        ];
    }
}
