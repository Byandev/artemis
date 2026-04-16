<?php

namespace App\Http\Controllers\API\Workspace;

use App\Http\Controllers\Controller;
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
        'delivered', 'returning_count', 'rmo_called', 'rmo_total_for_delivery',
        'rmo_productivity', 'rts_rate',
        'overall_engagement', 'new_cx_engagement', 'old_cx_engagement',
        'overall_orders', 'new_cx_orders', 'old_cx_orders',
        'new_cx_conversion_rate', 'old_cx_conversion_rate', 'overall_conversion_rate',
        'rmo_total_attempts', 'total_call_time',
    ];

    public function dailyRecords(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $from = $request->input('from')
            ? CarbonImmutable::parse($request->input('from'))->toDateString()
            : CarbonImmutable::now()->subDays(6)->toDateString();

        $to = $request->input('to')
            ? CarbonImmutable::parse($request->input('to'))->toDateString()
            : CarbonImmutable::now()->toDateString();

        $type = $request->input('type');

        $dailyReportsSub = DB::table('pancake_user_daily_reports')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('date', [$from, $to])
            ->when($type, fn ($q) => $q->where('type', $type))
            ->groupBy('pancake_user_id')
            ->selectRaw('
                pancake_user_id,
                SUM(total_orders) as total_orders,
                SUM(total_sales) as total_sales,
                SUM(delivered) as delivered,
                SUM(`returning`) as returning_count,
                SUM(rmo_called) as rmo_called
            ');

        $engagementSub = DB::table('pancake_user_daily_engagements')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('date', [$from, $to])
            ->groupBy('pancake_user_id')
            ->selectRaw('
                pancake_user_id,
                SUM(total_engagement) as s_total_eng,
                SUM(customer_engagement_new_inbox) as s_new_eng,
                SUM(order_count) as s_eng_orders,
                SUM(old_order_count) as s_old_orders
            ');

        $rmoSub = DB::table('pancake_order_for_delivery')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('delivery_date', [$from, $to])
            ->groupBy('conferrer_id')
            ->selectRaw('
                conferrer_id,
                COUNT(*) as rmo_total_for_delivery,
                SUM(customer_call_attempts) as s_customer_attempts,
                SUM(customer_call_duration) as s_customer_duration,
                SUM(rider_call_attempts) as s_rider_attempts,
                SUM(rider_call_duration) as s_rider_duration
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
            ->leftJoinSub($engagementSub, 'eng', 'eng.pancake_user_id', '=', 'pu.id')
            ->leftJoinSub($rmoSub, 'ofd_sum', 'ofd_sum.conferrer_id', '=', 'pu.id')
            ->selectRaw('
                pu.id as pancake_user_id,
                pu.name as csr_name,
                COALESCE(dr.total_orders, 0) as total_orders,
                COALESCE(dr.total_sales, 0) as total_sales,
                COALESCE(dr.delivered, 0) as delivered,
                COALESCE(dr.returning_count, 0) as returning_count,
                COALESCE(dr.rmo_called, 0) as rmo_called,
                COALESCE(eng.s_total_eng, 0) as overall_engagement,
                COALESCE(eng.s_new_eng, 0) as new_cx_engagement,
                COALESCE(eng.s_total_eng, 0) - COALESCE(eng.s_new_eng, 0) as old_cx_engagement,
                COALESCE(eng.s_eng_orders, 0) as overall_orders,
                COALESCE(eng.s_old_orders, 0) as old_cx_orders,
                COALESCE(eng.s_eng_orders, 0) - COALESCE(eng.s_old_orders, 0) as new_cx_orders,
                CASE
                    WHEN COALESCE(eng.s_new_eng, 0) > 0
                    THEN ROUND(((COALESCE(eng.s_eng_orders, 0) - COALESCE(eng.s_old_orders, 0)) / eng.s_new_eng) * 100, 2)
                    ELSE 0
                END as new_cx_conversion_rate,
                CASE
                    WHEN (COALESCE(eng.s_total_eng, 0) - COALESCE(eng.s_new_eng, 0)) > 0
                    THEN ROUND((COALESCE(eng.s_old_orders, 0) / (eng.s_total_eng - eng.s_new_eng)) * 100, 2)
                    ELSE 0
                END as old_cx_conversion_rate,
                CASE
                    WHEN COALESCE(eng.s_total_eng, 0) > 0
                    THEN ROUND((COALESCE(eng.s_eng_orders, 0) / eng.s_total_eng) * 100, 2)
                    ELSE 0
                END as overall_conversion_rate,
                COALESCE(ofd_sum.rmo_total_for_delivery, 0) as rmo_total_for_delivery,
                CASE
                    WHEN COALESCE(ofd_sum.rmo_total_for_delivery, 0) > 0
                    THEN ROUND((COALESCE(dr.rmo_called, 0) / ofd_sum.rmo_total_for_delivery) * 100, 2)
                    ELSE 0
                END as rmo_productivity,
                CASE
                    WHEN (COALESCE(dr.delivered, 0) + COALESCE(dr.returning_count, 0)) > 0
                    THEN ROUND((COALESCE(dr.returning_count, 0) / (dr.delivered + dr.returning_count)) * 100, 2)
                    ELSE 0
                END as rts_rate,
                COALESCE(ofd_sum.s_customer_attempts, 0) + COALESCE(ofd_sum.s_rider_attempts, 0) as rmo_total_attempts,
                COALESCE(ofd_sum.s_customer_duration, 0) + COALESCE(ofd_sum.s_rider_duration, 0) as total_call_time
            ');

        $records = QueryBuilder::for($query)
            ->allowedSorts(array_map(fn ($s) => AllowedSort::field($s), self::ALLOWED_SORTS))
            ->defaultSort('-total_sales')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return response()->json($records);
    }

    private function statQuery(Request $request, Workspace $workspace)
    {
        $from = $request->input('from')
            ? CarbonImmutable::parse($request->input('from'))->toDateString()
            : CarbonImmutable::now()->subDays(6)->toDateString();

        $to = $request->input('to')
            ? CarbonImmutable::parse($request->input('to'))->toDateString()
            : CarbonImmutable::now()->toDateString();

        $type = $request->input('type');

        return DB::table('pancake_user_daily_reports')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('date', [$from, $to])
            ->when($type, fn ($q) => $q->where('type', $type));
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
        $value = $this->statQuery($request, $workspace)->sum('rmo_called');

        return response()->json(['value' => $value]);
    }
}
