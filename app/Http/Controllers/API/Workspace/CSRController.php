<?php

namespace App\Http\Controllers\API\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $records = DB::table('pancake_user_daily_reports as cdr')
            ->join('pancake_users as pu', 'pu.id', '=', 'cdr.pancake_user_id')
            ->where('cdr.workspace_id', $workspace->id)
            ->whereBetween('cdr.date', [$from, $to])
            ->when($type, fn ($q) => $q->where('cdr.type', $type))
            ->groupBy('cdr.pancake_user_id', 'pu.name')
            ->selectRaw('
                cdr.pancake_user_id,
                pu.name as csr_name,
                SUM(cdr.total_orders) as total_orders,
                SUM(cdr.total_sales)  as total_sales,
                SUM(cdr.delivered)    as delivered,
                SUM(cdr.`returning`)  as returning_count,
                SUM(cdr.rmo_called)   as rmo_called,
                CASE
                    WHEN SUM(cdr.delivered) + SUM(cdr.`returning`) > 0
                    THEN ROUND((SUM(cdr.`returning`) / (SUM(cdr.delivered) + SUM(cdr.`returning`))) * 100, 2)
                    ELSE 0
                END as rts_rate
            ')
            ->orderByDesc('total_sales')
            ->get();

        return response()->json([
            'data' => $records,
            'filters' => ['from' => $from, 'to' => $to],
        ]);
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
