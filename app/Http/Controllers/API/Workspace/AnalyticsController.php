<?php

namespace App\Http\Controllers\API\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Modules\Pancake\Models\Order;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $workspace = Workspace::find($request->workspace->id);

        $data = \Cache::remember(json_encode($request->only(['date_range', 'filter'])), 5 * 60, function () use ($request, $workspace) {
            return $workspace->metrics($request->array('date_range', []), $request->array('filter' , []))
                ->extract(['rtsRate', 'aov', 'totalSales', 'totalOrders', 'repeatOrderRatio', 'timeToFirstOrder', 'avgLifetimeValue', 'avgDeliveryDays']);
        });

        return response()->json($data);
    }

    public function breakdown(Request $request)
    {
        $group = $request->input('group', 'monthly');

        $base = Order::query()
            ->whereHas('page', fn ($q) => $q->where('workspace_id', $request->workspace->id))
            ->whereNotNull('confirmed_at');

        $periodSql = match ($group) {
            'weekly' => "DATE_FORMAT(confirmed_at, '%x-W%v')",
            'monthly' => "DATE_FORMAT(confirmed_at, '%Y-%m')",
            default => 'DATE(confirmed_at)',
        };

        $totals = (clone $base)
            ->selectRaw("$periodSql as period, COUNT(*) as value")
            ->groupByRaw($periodSql)
            ->orderByRaw($periodSql)
            ->get();

        return response()->json(['data' => $totals]);
    }
}
