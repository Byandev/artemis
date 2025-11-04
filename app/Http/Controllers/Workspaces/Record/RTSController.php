<?php

namespace App\Http\Controllers\Workspaces\Record;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Workspace;
use DB;

class RTSController extends Controller
{
    public function index(Workspace $workspace)
    {
        return Order::selectRaw('
        DATE(confirmed_at) AS date,
        SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) AS delivered_count,
        SUM(CASE WHEN status IN (4,5) THEN 1 ELSE 0 END) AS returned_count,
        SUM(CASE WHEN status = 3 THEN total_amount ELSE 0 END) AS delivered_amount,
        SUM(CASE WHEN status IN (4,5) THEN total_amount ELSE 0 END) AS returned_amount,
        CAST(
            (SUM(CASE WHEN status IN (4,5) THEN 1 ELSE 0 END) * 100.0) /
            NULLIF(SUM(CASE WHEN status IN (3,4,5) THEN 1 ELSE 0 END), 0)
        AS FLOAT) AS rts_rate_percentage
    ')
            ->whereNotNull('confirmed_at')
            ->groupBy(DB::raw('DATE(confirmed_at)'))
            ->orderBy('date', 'asc')
            ->get();
    }
}
