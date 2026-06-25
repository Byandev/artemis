<?php

namespace App\Queries;

use App\Enums\Logging\LogStatus;
use App\Enums\Logging\LogType;
use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-side queries for monitoring dashboards and alerting. Each method maps to
 * one of the indexes declared on the `activity_logs` table so they stay fast as
 * the table grows.
 */
class ActivityLogMonitoringQuery
{
    /**
     * Recent failures across the whole system — the primary alerting feed.
     * Uses the (status, created_at) index.
     *
     * @return Collection<int, ActivityLog>
     */
    public function recentFailures(int $hours = 1, int $limit = 100): Collection
    {
        return ActivityLog::query()
            ->failures()
            ->recent($hours)
            ->with('user:id,name,email')
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Every failed scheduled / triggered job in the window, newest first.
     * Uses the (log_type, category, created_at) + (status,...) indexes.
     *
     * @return Collection<int, ActivityLog>
     */
    public function jobFailures(int $hours = 24): Collection
    {
        return ActivityLog::query()
            ->ofType(LogType::System)
            ->failures()
            ->recent($hours)
            ->latest('created_at')
            ->get(['id', 'job_name', 'trigger_type', 'schedule', 'message', 'error_detail', 'created_at']);
    }

    /**
     * Failure history for a single job — health over time for one cron task.
     * Uses the (job_name, status, created_at) index.
     *
     * @return Collection<int, ActivityLog>
     */
    public function jobFailureHistory(string $jobName, int $days = 30): Collection
    {
        return ActivityLog::query()
            ->forJob($jobName)
            ->failures()
            ->where('created_at', '>=', now()->subDays($days))
            ->latest('created_at')
            ->get();
    }

    /**
     * Per-job success/failure tallies so a dashboard can flag flaky jobs.
     *
     * @return Collection<int, object{job_name: string, runs: int, failures: int, last_run: string}>
     */
    public function jobReliability(int $days = 7): Collection
    {
        return ActivityLog::query()
            ->ofType(LogType::System)
            ->whereNotNull('job_name')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('job_name')
            ->orderByDesc('failures')
            ->get([
                'job_name',
                DB::raw('COUNT(*) as runs'),
                DB::raw('SUM(status = "failure") as failures'),
                DB::raw('MAX(created_at) as last_run'),
            ]);
    }

    /**
     * Daily activity summary grouped by date / type / category / status —
     * powers stacked-bar trend charts. Uses the (log_type, category, created_at)
     * index for the range scan.
     *
     * @return Collection<int, object{day: string, log_type: string, category: string, status: string, total: int}>
     */
    public function dailyActivitySummary(int $days = 14): Collection
    {
        return ActivityLog::query()
            ->where('created_at', '>=', now()->subDays($days)->startOfDay())
            ->groupBy('day', 'log_type', 'category', 'status')
            ->orderBy('day')
            ->get([
                DB::raw('DATE(created_at) as day'),
                'log_type',
                'category',
                'status',
                DB::raw('COUNT(*) as total'),
            ]);
    }

    /**
     * Chronological audit trail for one user.
     * Uses the (user_id, created_at) index.
     *
     * @return LengthAwarePaginator<int, ActivityLog>
     */
    public function userAuditTrail(int $userId, int $perPage = 50)
    {
        return ActivityLog::query()
            ->forUser($userId)
            ->latest('created_at')
            ->paginate($perPage);
    }

    /**
     * Failure counts grouped by category for the window — quick health overview.
     * Uses the (category, status) index.
     *
     * @return Collection<int, object{category: string, failures: int}>
     */
    public function failureCountsByCategory(int $hours = 24): Collection
    {
        return ActivityLog::query()
            ->failures()
            ->recent($hours)
            ->groupBy('category')
            ->orderByDesc('failures')
            ->get([
                'category',
                DB::raw('COUNT(*) as failures'),
            ]);
    }

    /**
     * True when any failure has occurred in the last N minutes — a cheap,
     * index-only check suitable for a frequent alerting heartbeat.
     */
    public function hasRecentFailures(int $minutes = 15): bool
    {
        return ActivityLog::query()
            ->where('status', LogStatus::Failure->value)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->exists();
    }
}
