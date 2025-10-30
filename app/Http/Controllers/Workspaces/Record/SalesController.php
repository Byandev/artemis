<?php

namespace App\Http\Controllers\Workspaces\Record;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Workspace;
use DB;

class SalesController extends Controller
{
    public function index(Workspace $workspace)
    {
        return Order::selectRaw('
                DATE(confirmed_at) AS date,
                SUM(total_amount) AS total_sales
            ')
            ->whereNotNull('confirmed_at')
            ->groupBy(DB::raw('DATE(confirmed_at)'))
            ->orderBy('date', 'asc')
            ->get();
    }
}
