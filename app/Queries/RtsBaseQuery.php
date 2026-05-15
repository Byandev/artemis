<?php

namespace App\Queries;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Pancake\Models\Order;

abstract class RtsBaseQuery
{
    protected const METRICS_SQL = '
        SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END) AS total_orders,
        SUM(CASE WHEN pancake_orders.status = 3 THEN 1 ELSE 0 END) AS delivered_count,
        SUM(CASE WHEN pancake_orders.status IN (4,5) THEN 1 ELSE 0 END) AS returned_count,
        ROUND(
            (SUM(CASE WHEN pancake_orders.status IN (4,5) THEN 1 ELSE 0 END) * 100.0) /
            NULLIF(SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END), 0),
            2
        ) AS rts_rate_percentage
    ';

    protected const HAVING_SQL = 'SUM(CASE WHEN pancake_orders.status IN (3,4,5) THEN 1 ELSE 0 END) > 0';

    protected Builder $query;

    public function __construct(
        protected readonly Workspace $workspace,
        protected readonly Request $request,
    ) {
        $this->query = Order::query();
        $this->applyWorkspaceFilter();
        $this->applyStatusFilter();
        $this->applyDateFilter();
        $this->applyEntityFilters();
    }

    private function applyWorkspaceFilter(): void
    {
        $this->query->where('pancake_orders.workspace_id', $this->workspace->id);
    }

    /**
     * Pre-filter to delivered/returned statuses only.
     * Reduces rows before GROUP BY since METRICS_SQL only counts these statuses anyway.
     */
    private function applyStatusFilter(): void
    {
        $this->query->whereIn('pancake_orders.status', [3, 4, 5]);
    }

    /**
     * OR across two indexed columns prevents single-index use. Split into a UNION of IDs
     * (one branch per date column, each uses its own index) and join back.
     */
    private function applyDateFilter(): void
    {
        $start = $this->request->input('start_date');
        $end = $this->request->input('end_date');

        if (! $start || ! $end) {
            return;
        }

        $endInclusive = $end.' 23:59:59';
        $workspaceId = $this->workspace->id;

        $pageIds = $this->request->filled('page_ids')
            ? (array) $this->request->input('page_ids')
            : null;
        $shopIds = $this->request->filled('shop_ids')
            ? (array) $this->request->input('shop_ids')
            : null;
        $teamIds = $this->request->filled('team_ids')
            ? (array) $this->request->input('team_ids')
            : null;

        $branch = fn (string $dateColumn) => DB::table('pancake_orders')
            ->selectRaw('id AS event_order_id')
            ->where('workspace_id', $workspaceId)
            ->whereIn('status', [3, 4, 5])
            ->whereBetween($dateColumn, [$start, $endInclusive])
            ->when($pageIds, fn ($q) => $q->whereIn('page_id', $pageIds))
            ->when($shopIds, fn ($q) => $q->whereIn('shop_id', $shopIds))
            ->when($teamIds, fn ($q) => $q->whereIn('page_id', $this->teamPageIdsSubquery($teamIds)));

        $eventIds = $branch('delivered_at')->union($branch('returning_at'));

        $this->query->joinSub($eventIds, 'event_ids', 'event_ids.event_order_id', '=', 'pancake_orders.id');
    }

    private function applyEntityFilters(): void
    {
        if ($this->request->filled('page_ids')) {
            $this->query->whereIn('pancake_orders.page_id', (array) $this->request->input('page_ids'));
        }

        if ($this->request->filled('shop_ids')) {
            $this->query->whereIn('pancake_orders.shop_id', (array) $this->request->input('shop_ids'));
        }

        if ($this->request->filled('team_ids')) {
            $this->query->whereIn(
                'pancake_orders.page_id',
                $this->teamPageIdsSubquery((array) $this->request->input('team_ids')),
            );
        }
    }

    private function teamPageIdsSubquery(array $teamIds)
    {
        return DB::table('pages')
            ->select('id')
            ->where('workspace_id', $this->workspace->id)
            ->whereIn('owner_id', function ($sub) use ($teamIds) {
                $sub->from('team_user')
                    ->select('user_id')
                    ->whereIn('team_id', $teamIds);
            });
    }
}
