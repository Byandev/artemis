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
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class CSRController extends Controller
{
    private const ALLOWED_SORTS = [
        'csr_name', 'total_orders', 'total_sales',
        'delivered', 'returning_count', 'rts_rate',
        'total_called', 'total_call_time',
    ];

    private function reportTable(Request $request): string
    {
        $type = strtolower((string) $request->input('type', 'pos'));

        return $type === 'erp'
            ? (new PancakeUserErpDailyReport)->getTable()
            : (new PancakeUserPosDailyReport)->getTable();
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

    public function dailyRecords(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        [$from, $to] = $this->range($request);
        $reportTable = $this->reportTable($request);

        $dailyReportsSub = DB::table($reportTable)
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

        $rmoSub = DB::table('pancake_user_rmo_daily_reports')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('date', [$from, $to])
            ->groupBy('pancake_user_id')
            ->selectRaw('
                pancake_user_id,
                SUM(total_called) as total_called,
                SUM(total_call_time) as total_call_time
            ');

        $query = PancakeUser::query()
            ->from('pancake_users as pu')
            ->whereExists(function ($q) use ($workspace) {
                $q->select(DB::raw(1))
                    ->from('pancake_shop_users as psu')
                    ->join('shops as s', 's.id', '=', 'psu.shop_id')
                    ->whereColumn('psu.user_id', 'pu.id')
                    ->where('s.workspace_id', $workspace->id);
            })
            ->leftJoinSub($dailyReportsSub, 'dr', 'dr.pancake_user_id', '=', 'pu.id')
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

        $records = QueryBuilder::for($query)
            ->allowedFilters([
                AllowedFilter::callback('search', function ($q, $value) {
                    $q->where('pu.name', 'like', "%{$value}%");
                }),
            ])
            ->allowedSorts(array_map(fn ($s) => AllowedSort::field($s), self::ALLOWED_SORTS))
            ->defaultSort('-total_sales')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return response()->json($records);
    }

    private function statQuery(Request $request, Workspace $workspace)
    {
        [$from, $to] = $this->range($request);

        return DB::table($this->reportTable($request))
            ->where('workspace_id', $workspace->id)
            ->whereBetween('date', [$from, $to]);
    }

    public function statTotalSales(Request $request, Workspace $workspace)
    {
        $value = $this->statQuery($request, $workspace)->sum('total_sales');

        return response()->json(['value' => $value]);
    }

    public function statTotalOrders(Request $request, Workspace $workspace)
    {
        $value = $this->statQuery($request, $workspace)->sum('total_orders');

        return response()->json(['value' => $value]);
    }

    public function statTotalDelivered(Request $request, Workspace $workspace)
    {
        $value = $this->statQuery($request, $workspace)->sum('delivered');

        return response()->json(['value' => $value]);
    }

    public function statTotalReturning(Request $request, Workspace $workspace)
    {
        $value = $this->statQuery($request, $workspace)->sum('returning');

        return response()->json(['value' => $value]);
    }

    public function statTotalRts(Request $request, Workspace $workspace)
    {
        $row = $this->statQuery($request, $workspace)
            ->selectRaw('SUM(delivered) as d, SUM(`returning`) as r')
            ->first();

        $total = ($row->d ?? 0) + ($row->r ?? 0);
        $value = $total > 0 ? round(($row->r / $total) * 100, 2) : 0;

        return response()->json(['value' => $value]);
    }

    public function statTotalRmoCalled(Request $request, Workspace $workspace)
    {
        [$from, $to] = $this->range($request);

        $value = DB::table('pancake_user_rmo_daily_reports')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('date', [$from, $to])
            ->sum('total_called');

        return response()->json(['value' => $value]);
    }
}
