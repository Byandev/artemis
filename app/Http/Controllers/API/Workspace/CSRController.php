<?php

namespace App\Http\Controllers\API\Workspace;

use App\Http\Controllers\Controller;
use App\Models\PancakeUserDailyReport;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class CSRController extends Controller
{
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

        $query = PancakeUserDailyReport::query()
            ->join('pancake_users as pu', 'pu.id', '=', 'pancake_user_daily_reports.pancake_user_id')
            ->where('pancake_user_daily_reports.workspace_id', $workspace->id)
            ->whereBetween('pancake_user_daily_reports.date', [$from, $to])
            ->when($type, fn ($q) => $q->where('pancake_user_daily_reports.type', $type))
            ->groupBy('pancake_user_daily_reports.pancake_user_id', 'pu.name')
            ->selectRaw('
                pancake_user_daily_reports.pancake_user_id,
                pu.name as csr_name,
                SUM(pancake_user_daily_reports.total_orders) as total_orders,
                SUM(pancake_user_daily_reports.total_sales)  as total_sales,
                SUM(pancake_user_daily_reports.delivered)    as delivered,
                SUM(pancake_user_daily_reports.`returning`)  as returning_count,
                SUM(pancake_user_daily_reports.rmo_called)   as rmo_called,
                CASE
                    WHEN SUM(pancake_user_daily_reports.delivered) + SUM(pancake_user_daily_reports.`returning`) > 0
                    THEN ROUND((SUM(pancake_user_daily_reports.`returning`) / (SUM(pancake_user_daily_reports.delivered) + SUM(pancake_user_daily_reports.`returning`))) * 100, 2)
                    ELSE 0
                END as rts_rate
            ');

        $records = QueryBuilder::for($query)
            ->allowedSorts([
                AllowedSort::field('csr_name'),
                AllowedSort::field('total_orders'),
                AllowedSort::field('total_sales'),
                AllowedSort::field('delivered'),
                AllowedSort::field('returning_count'),
                AllowedSort::field('rmo_called'),
                AllowedSort::field('rts_rate'),
            ])
            ->defaultSort('-total_sales')
            ->paginate($request->integer('per_page', 15))
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
