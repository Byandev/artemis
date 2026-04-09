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

        $records = DB::table('csr_daily_records as cdr')
            ->join('users as u', 'u.id', '=', 'cdr.csr_id')
            ->where('cdr.workspace_id', $workspace->id)
            ->whereBetween('cdr.date', [$from, $to])
            ->groupBy('cdr.csr_id', 'u.name')
            ->selectRaw('
                cdr.csr_id,
                u.name as csr_name,
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
            'data'    => $records,
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }
}