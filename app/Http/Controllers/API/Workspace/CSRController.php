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

    private function timestampRange(Request $request): array
    {
        [$from, $to] = $this->range($request);

        return [$from.' 00:00:00', $to.' 23:59:59'];
    }

    private function rmoDeliveryAggregateSub(Workspace $workspace, string $from, string $to)
    {
        return DB::table('pancake_order_for_delivery as pofd')
            ->whereNotNull('pofd.assignee_id')
            ->where('pofd.workspace_id', $workspace->id)
            ->whereBetween('pofd.delivery_date', [$from, $to])
            ->groupBy('pofd.assignee_id')
            ->selectRaw('
                pofd.assignee_id as pancake_user_id,
                SUM(
                    CASE WHEN EXISTS (
                        SELECT 1
                        FROM call_logs cl
                        WHERE cl.workspace_id = pofd.workspace_id
                          AND cl.user_id = pofd.assignee_id
                          AND cl.call_date = pofd.delivery_date
                          AND cl.phone_number IN (pofd.rider_phone, pofd.customer_phone)
                    ) THEN 1 ELSE 0 END
                ) as total_called,
                COALESCE(SUM(pofd.customer_call_duration), 0) + COALESCE(SUM(pofd.rider_call_duration), 0) as total_call_time
            ');
    }

    private function posOrdersAggregateSub(Workspace $workspace, string $start, string $end)
    {
        return DB::table('pancake_orders')
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('confirmed_by')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('confirmed_at', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->where('status', 3)->whereBetween('delivered_at', [$start, $end]);
                    })
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->whereIn('status', [4, 5])->whereBetween('returning_at', [$start, $end]);
                    });
            })
            ->groupBy('confirmed_by')
            ->selectRaw('
                confirmed_by as pancake_user_id,
                SUM(CASE WHEN confirmed_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as total_orders,
                SUM(CASE WHEN confirmed_at BETWEEN ? AND ? THEN final_amount ELSE 0 END) as total_sales,
                SUM(CASE WHEN status = 3 AND delivered_at BETWEEN ? AND ? THEN final_amount ELSE 0 END) as delivered,
                SUM(CASE WHEN status IN (4,5) AND returning_at BETWEEN ? AND ? THEN final_amount ELSE 0 END) as returning_count
            ', [$start, $end, $start, $end, $start, $end, $start, $end]);
    }

    public function dailyRecords(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        [$from, $to] = $this->range($request);

        if ($this->isPos($request)) {
            [$start, $end] = $this->timestampRange($request);
            $dailyReportsSub = $this->posOrdersAggregateSub($workspace, $start, $end);
        } else {
            $dailyReportsSub = DB::table($this->reportTable($request))
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

        $rmoSub = $this->rmoDeliveryAggregateSub($workspace, $from, $to);

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

    private function posOrdersStatQuery(Workspace $workspace, string $dateColumn, string $start, string $end, ?array $statuses = null)
    {
        $query = DB::table('pancake_orders')
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('confirmed_by')
            ->whereBetween($dateColumn, [$start, $end]);

        if ($statuses !== null) {
            $query->whereIn('status', $statuses);
        }

        return $query;
    }

    public function statTotalSales(Request $request, Workspace $workspace)
    {
        if ($this->isPos($request)) {
            [$start, $end] = $this->timestampRange($request);
            $value = $this->posOrdersStatQuery($workspace, 'confirmed_at', $start, $end)->sum('final_amount');
        } else {
            $value = $this->rollupStatQuery($request, $workspace)->sum('total_sales');
        }

        return response()->json(['value' => $value]);
    }

    public function statTotalOrders(Request $request, Workspace $workspace)
    {
        if ($this->isPos($request)) {
            [$start, $end] = $this->timestampRange($request);
            $value = $this->posOrdersStatQuery($workspace, 'confirmed_at', $start, $end)->count();
        } else {
            $value = $this->rollupStatQuery($request, $workspace)->sum('total_orders');
        }

        return response()->json(['value' => $value]);
    }

    public function statTotalDelivered(Request $request, Workspace $workspace)
    {
        if ($this->isPos($request)) {
            [$start, $end] = $this->timestampRange($request);
            $value = $this->posOrdersStatQuery($workspace, 'delivered_at', $start, $end, [3])->sum('final_amount');
        } else {
            $value = $this->rollupStatQuery($request, $workspace)->sum('delivered');
        }

        return response()->json(['value' => $value]);
    }

    public function statTotalReturning(Request $request, Workspace $workspace)
    {
        if ($this->isPos($request)) {
            [$start, $end] = $this->timestampRange($request);
            $value = $this->posOrdersStatQuery($workspace, 'returning_at', $start, $end, [4, 5])->sum('final_amount');
        } else {
            $value = $this->rollupStatQuery($request, $workspace)->sum('returning');
        }

        return response()->json(['value' => $value]);
    }

    public function statTotalRts(Request $request, Workspace $workspace)
    {
        if ($this->isPos($request)) {
            [$start, $end] = $this->timestampRange($request);

            $delivered = $this->posOrdersStatQuery($workspace, 'delivered_at', $start, $end, [3])->sum('final_amount');
            $returning = $this->posOrdersStatQuery($workspace, 'returning_at', $start, $end, [4, 5])->sum('final_amount');

            $total = $delivered + $returning;
            $value = $total > 0 ? round(($returning / $total) * 100, 2) : 0;
        } else {
            $row = $this->rollupStatQuery($request, $workspace)
                ->selectRaw('SUM(delivered) as d, SUM(`returning`) as r')
                ->first();

            $total = ($row->d ?? 0) + ($row->r ?? 0);
            $value = $total > 0 ? round(($row->r / $total) * 100, 2) : 0;
        }

        return response()->json(['value' => $value]);
    }

    public function statTotalRmoCalled(Request $request, Workspace $workspace)
    {
        [$from, $to] = $this->range($request);

        $value = DB::table('pancake_order_for_delivery as pofd')
            ->whereNotNull('pofd.assignee_id')
            ->where('pofd.workspace_id', $workspace->id)
            ->whereBetween('pofd.delivery_date', [$from, $to])
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('call_logs as cl')
                    ->whereColumn('cl.workspace_id', 'pofd.workspace_id')
                    ->whereColumn('cl.user_id', 'pofd.assignee_id')
                    ->whereColumn('cl.call_date', 'pofd.delivery_date')
                    ->whereRaw('cl.phone_number IN (pofd.rider_phone, pofd.customer_phone)');
            })
            ->count();

        return response()->json(['value' => $value]);
    }
}
