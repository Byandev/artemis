<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\Logging\LogCategory;
use App\Enums\Logging\LogStatus;
use App\Enums\Logging\LogType;
use App\Enums\Logging\TriggerType;
use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Shared activity-log listing logic for the workspace-scoped and global-admin
 * controllers. Keeps filtering, sorting and the summary identical across both.
 */
trait BuildsActivityLogQuery
{
    /**
     * Paginated, filtered log list built off a pre-scoped base query.
     *
     * @return LengthAwarePaginator<int, ActivityLog>
     */
    protected function paginateActivityLogs(Builder $base, Request $request, bool $withWorkspace = false): LengthAwarePaginator
    {
        $relations = ['user:id,name,email'];
        if ($withWorkspace) {
            $relations[] = 'workspace:id,name,slug';
        }

        return QueryBuilder::for($base)
            ->with($relations)
            ->allowedFilters([
                AllowedFilter::exact('log_type'),
                AllowedFilter::exact('category'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('trigger_type'),
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('workspace_id'),
                AllowedFilter::exact('job_name'),
                AllowedFilter::callback('search', fn (Builder $query, $value) => $query->where(function (Builder $q) use ($value) {
                    $q->where('message', 'like', "%{$value}%")
                        ->orWhere('action_type', 'like', "%{$value}%")
                        ->orWhere('job_name', 'like', "%{$value}%")
                        ->orWhere('error_detail', 'like', "%{$value}%");
                })),
                AllowedFilter::callback('start_date', fn (Builder $query, $value) => $query->whereDate('created_at', '>=', $value)),
                AllowedFilter::callback('end_date', fn (Builder $query, $value) => $query->whereDate('created_at', '<=', $value)),
            ])
            ->allowedSorts([
                AllowedSort::field('created_at'),
                AllowedSort::field('status'),
                AllowedSort::field('category'),
                AllowedSort::field('log_type'),
            ])
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();
    }

    /**
     * Summary counts for the stat cards. Each stat runs its own query against a
     * base that honours the active search/filter *context* (search, type,
     * category, dates, workspace) — but NOT the `status` facet, so the
     * per-status "Failures" card stays meaningful while filtering.
     *
     * @return array{total: int, failures: int, last_24h: int}
     */
    protected function activityLogSummary(Builder $base, Request $request): array
    {
        $this->applySummaryContext($base, $request);

        // Total within the current search/filter context.
        $total = (clone $base)->count();

        // Failures within the same context.
        $failures = (clone $base)
            ->where('status', LogStatus::Failure->value)
            ->count();

        // Activity in the last 24 hours within the same context.
        $last24h = (clone $base)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return [
            'total' => $total,
            'failures' => $failures,
            'last_24h' => $last24h,
        ];
    }

    /**
     * Apply the contextual (non-status) filters to a summary base query so the
     * stat cards reflect what the user is currently searching/filtering.
     */
    protected function applySummaryContext(Builder $query, Request $request): void
    {
        $filter = (array) $request->input('filter', []);

        if (! empty($filter['search'])) {
            $value = $filter['search'];
            $query->where(function (Builder $q) use ($value) {
                $q->where('message', 'like', "%{$value}%")
                    ->orWhere('action_type', 'like', "%{$value}%")
                    ->orWhere('job_name', 'like', "%{$value}%")
                    ->orWhere('error_detail', 'like', "%{$value}%");
            });
        }

        foreach (['log_type', 'category', 'trigger_type', 'user_id', 'workspace_id', 'job_name'] as $exact) {
            if (! empty($filter[$exact])) {
                $query->where($exact, $filter[$exact]);
            }
        }

        if (! empty($filter['start_date'])) {
            $query->whereDate('created_at', '>=', $filter['start_date']);
        }

        if (! empty($filter['end_date'])) {
            $query->whereDate('created_at', '<=', $filter['end_date']);
        }
    }

    /**
     * Enum option lists for the frontend filter dropdowns.
     *
     * @return array<string, array<int, string>>
     */
    protected function activityLogFilterOptions(): array
    {
        return [
            'log_types' => array_map(fn (LogType $t) => $t->value, LogType::cases()),
            'statuses' => array_map(fn (LogStatus $s) => $s->value, LogStatus::cases()),
            'categories' => array_map(fn (LogCategory $c) => $c->value, LogCategory::cases()),
            'trigger_types' => array_map(fn (TriggerType $t) => $t->value, TriggerType::cases()),
        ];
    }
}
