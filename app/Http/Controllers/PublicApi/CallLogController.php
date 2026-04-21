<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pancake\Models\OrderForDelivery;

class CallLogController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required'],
            'call_logs' => ['required', 'array', 'min:1'],
            'call_logs.*.phone_number' => ['required', 'string'],
            'call_logs.*.type' => ['required', 'string'],
            'call_logs.*.duration' => ['required', 'integer', 'min:0'],
            'call_logs.*.timestamp' => ['required', 'date'],
        ]);

        $workspace = $request->attributes->get('workspace');
        $now = now();

        $rows = array_map(fn ($log) => [
            'workspace_id' => $workspace->id,
            'user_id' => $request->input('user_id'),
            'phone_number' => $log['phone_number'],
            'type' => $log['type'],
            'duration' => $log['duration'],
            'called_at' => Carbon::parse($log['timestamp']),
            'created_at' => $now,
            'updated_at' => $now,
        ], $request->input('call_logs'));

        foreach (array_chunk($rows, 500) as $chunk) {
            CallLog::insert($chunk);
        }

        return response()->json([
            'total' => count($rows),
        ]);
    }

    public function kpi(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'uuid'],
        ]);

        $workspace = $request->attributes->get('workspace');

        $deliveries = OrderForDelivery::where('workspace_id', $workspace->id)
            ->where('assignee_id', $request->input('user_id'))
            ->whereDate('delivery_date', $request->input('date', now()->toDateString()))
            ->withCount(['customerCallLogs', 'riderCallLogs'])
            ->get();

        $totalOrders = $deliveries->count();
        $customersCalled = $deliveries->where('customer_call_logs_count', '>', 0)->count();
        $ridersCalled = $deliveries->where('rider_call_logs_count', '>', 0)->count();
        $totalCalled = $deliveries->filter(fn ($d) => $d->customer_call_logs_count > 0 || $d->rider_call_logs_count > 0)->count();
        $totalAttempts = $deliveries->sum('customer_call_logs_count') + $deliveries->sum('rider_call_logs_count');

        return response()->json([
            'total_called' => $totalCalled,
            'total_orders' => $totalOrders,
            'riders_called' => $ridersCalled,
            'customers_called' => $customersCalled,
            'total_attempts' => $totalAttempts,
        ]);
    }
}