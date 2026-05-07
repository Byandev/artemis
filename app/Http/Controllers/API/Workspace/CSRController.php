<?php

namespace App\Http\Controllers\API\Workspace;

use App\Http\Controllers\Controller;
use App\Models\PancakeUserErpDailyReport;
use App\Models\PancakeUserPosDailyReport;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Pancake\Models\User as PancakeUser;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class CSRController extends Controller
{
    private const ALLOWED_SORTS = [
        'csr_name', 'total_orders', 'total_sales',
        'delivered', 'returning_count', 'rts_rate',
        'total_called', 'total_call_time',
    ];

    private function isPos(Request $request): bool
    {
        return strtolower((string) $request->input('type', 'pos')) !== 'erp';
    }

    private function reportTable(Request $request): string
    {
        return $this->isPos($request)
            ? (new PancakeUserPosDailyReport)->getTable()
            : (new PancakeUserErpDailyReport)->getTable();
    }

    private function range(Request $request): array
    {
        $from = $request->input('from')
            ? CarbonImmutable::parse($request->input('from'))->toDateString()
            : CarbonImmutable::now()->subDays(6)->toDateString();

        $to = $request->input('to')
            ? CarbonImmutable::parse($request->input('to'))->toDateString()
            : CarbonImmutable::now()->toDateString();

        return [$from, $to];
    }

    private function posDailySummarySub(Workspace $workspace, string $from, string $to)
    {
        // csr_daily_records is the nightly POS rollup written by sync:csr-daily-records.
        // It already aggregates pancake_orders per (workspace, csr, date) with rmo_called
        // counted as pancake_order_for_delivery rows where status != 'PENDING'.
        // csr_id here is the system users.id, joined later via pancake_users.user_id.
        return DB::table('csr_daily_records')
            ->where('workspace_id', $workspace->id)
            ->where('type', 'POS')
            ->whereBetween('date', [$from, $to])
            ->groupBy('csr_id')
            ->selectRaw('
                csr_id,
                SUM(total_orders) as total_orders,
                SUM(total_sales) as total_sales,
                SUM(delivered) as delivered,
                SUM(`returning`) as returning_count,
                SUM(rmo_called) as total_called
            ');
    }

    private function erpDailySummarySub(Workspace $workspace, string $from, string $to)
    {
        return DB::table('pancake_user_erp_daily_reports')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('date', [$from, $to])
            ->groupBy('pancake_user_id')
            ->selectRaw('
                pancake_user_id,
                SUM(total_orders) as total_orders,
                SUM(total_sales) as total_sales,
                SUM(delivered) as delivered,
                SUM(`returning`) as returning_count
            ');
    }

    private function rmoCallTimeSub(Workspace $workspace, string $from, string $to)
    {
        return DB::table('pancake_user_rmo_daily_reports')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('date', [$from, $to])
            ->groupBy('pancake_user_id')
            ->selectRaw('
                pancake_user_id,
                SUM(total_call_time) as total_call_time,
                SUM(total_called) as total_called
            ');
    }

    public function dailyRecords(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        [$from, $to] = $this->range($request);

        $rmoSub = $this->rmoCallTimeSub($workspace, $from, $to);

        if ($this->isPos($request)) {
            // POS rollup is keyed by csr_id (= users.id), so the LEFT JOIN keys
            // off pancake_users.user_id. total_called comes pre-aggregated from
            // csr_daily_records.rmo_called; total_call_time comes from the rmo
            // rollup (which is keyed on pancake_user_id).
            $drSub = $this->posDailySummarySub($workspace, $from, $to);

            $query = PancakeUser::query()
                ->from('pancake_users as pu')
                ->whereExists(function ($q) use ($workspace) {
                    $q->select(DB::raw(1))
                        ->from('pancake_shop_users as psu')
                        ->join('shops as s', 's.id', '=', 'psu.shop_id')
                        ->whereColumn('psu.user_id', 'pu.id')
                        ->where('s.workspace_id', $workspace->id);
                })
                ->leftJoinSub($drSub, 'dr', 'dr.csr_id', '=', 'pu.user_id')
                ->leftJoinSub($rmoSub, 'rmo', 'rmo.pancake_user_id', '=', 'pu.id')
                ->selectRaw('
                    pu.id as pancake_user_id,
                    pu.name as csr_name,
                    COALESCE(dr.total_orders, 0) as total_orders,
                    COALESCE(dr.total_sales, 0) as total_sales,
                    COALESCE(dr.delivered, 0) as delivered,
                    COALESCE(dr.returning_count, 0) as returning_count,
                    COALESCE(dr.total_called, 0) as total_called,
                    COALESCE(rmo.total_call_time, 0) as total_call_time,
                    CASE
                        WHEN (COALESCE(dr.delivered, 0) + COALESCE(dr.returning_count, 0)) > 0
                        THEN ROUND((COALESCE(dr.returning_count, 0) / (dr.delivered + dr.returning_count)) * 100, 2)
                        ELSE 0
                    END as rts_rate
                ');
        } else {
            // ERP rollup is keyed by pancake_user_id; total_called for ERP comes
            // from the rmo rollup since pancake_user_erp_daily_reports doesn't
            // store it.
            $drSub = $this->erpDailySummarySub($workspace, $from, $to);

            $query = PancakeUser::query()
                ->from('pancake_users as pu')
                ->whereExists(function ($q) use ($workspace) {
                    $q->select(DB::raw(1))
                        ->from('pancake_shop_users as psu')
                        ->join('shops as s', 's.id', '=', 'psu.shop_id')
                        ->whereColumn('psu.user_id', 'pu.id')
                        ->where('s.workspace_id', $workspace->id);
                })
                ->leftJoinSub($drSub, 'dr', 'dr.pancake_user_id', '=', 'pu.id')
                ->leftJoinSub($rmoSub, 'rmo', 'rmo.pancake_user_id', '=', 'pu.id')
                ->selectRaw('
                    pu.id as pancake_user_id,
                    pu.name as csr_name,
                    COALESCE(dr.total_orders, 0) as total_orders,
                    COALESCE(dr.total_sales, 0) as total_sales,
                    COALESCE(dr.delivered, 0) as delivered,
                    COALESCE(dr.returning_count, 0) as returning_count,
                    COALESCE(rmo.total_called, 0) as total_called,
                    COALESCE(rmo.total_call_time, 0) as total_call_time,
                    CASE
                        WHEN (COALESCE(dr.delivered, 0) + COALESCE(dr.returning_count, 0)) > 0
                        THEN ROUND((COALESCE(dr.returning_count, 0) / (dr.delivered + dr.returning_count)) * 100, 2)
                        ELSE 0
                    END as rts_rate
                ');
        }

        $records = QueryBuilder::for($query)
            ->allowedSorts(array_map(fn ($s) => AllowedSort::field($s), self::ALLOWED_SORTS))
            ->defaultSort('-total_sales')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return response()->json($records);
    }

    private function rollupStatQuery(Request $request, Workspace $workspace)
    {
        [$from, $to] = $this->range($request);

        return DB::table($this->reportTable($request))
            ->where('workspace_id', $workspace->id)
            ->whereBetween('date', [$from, $to]);
    }

    private function posSummaryQuery(Request $request, Workspace $workspace)
    {
        [$from, $to] = $this->range($request);

        return DB::table('csr_daily_records')
            ->where('workspace_id', $workspace->id)
            ->where('type', 'POS')
            ->whereBetween('date', [$from, $to]);
    }

    public function statTotalSales(Request $request, Workspace $workspace)
    {
        $value = $this->isPos($request)
            ? $this->posSummaryQuery($request, $workspace)->sum('total_sales')
            : $this->rollupStatQuery($request, $workspace)->sum('total_sales');

        return response()->json(['value' => $value]);
    }

    public function statTotalOrders(Request $request, Workspace $workspace)
    {
        $value = $this->isPos($request)
            ? $this->posSummaryQuery($request, $workspace)->sum('total_orders')
            : $this->rollupStatQuery($request, $workspace)->sum('total_orders');

        return response()->json(['value' => $value]);
    }

    public function statTotalDelivered(Request $request, Workspace $workspace)
    {
        $value = $this->isPos($request)
            ? $this->posSummaryQuery($request, $workspace)->sum('delivered')
            : $this->rollupStatQuery($request, $workspace)->sum('delivered');

        return response()->json(['value' => $value]);
    }

    public function statTotalReturning(Request $request, Workspace $workspace)
    {
        $value = $this->isPos($request)
            ? $this->posSummaryQuery($request, $workspace)->sum('returning')
            : $this->rollupStatQuery($request, $workspace)->sum('returning');

        return response()->json(['value' => $value]);
    }

    public function statTotalRts(Request $request, Workspace $workspace)
    {
        $row = ($this->isPos($request)
            ? $this->posSummaryQuery($request, $workspace)
            : $this->rollupStatQuery($request, $workspace))
            ->selectRaw('SUM(delivered) as d, SUM(`returning`) as r')
            ->first();

        $total = ($row->d ?? 0) + ($row->r ?? 0);
        $value = $total > 0 ? round(($row->r / $total) * 100, 2) : 0;

        return response()->json(['value' => $value]);
    }

    public function statTotalRmoCalled(Request $request, Workspace $workspace)
    {
        [$from, $to] = $this->range($request);

        if ($this->isPos($request)) {
            // POS rmo_called is pre-aggregated nightly into csr_daily_records
            // (count of pancake_order_for_delivery rows where status != 'PENDING').
            $value = DB::table('csr_daily_records')
                ->where('workspace_id', $workspace->id)
                ->where('type', 'POS')
                ->whereBetween('date', [$from, $to])
                ->sum('rmo_called');
        } else {
            $value = DB::table('pancake_user_rmo_daily_reports')
                ->where('workspace_id', $workspace->id)
                ->whereBetween('date', [$from, $to])
                ->sum('total_called');
        }

        return response()->json(['value' => $value]);
    }
}
