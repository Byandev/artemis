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

        $rows = array_map(function ($log) use ($workspace, $request, $now) {
            $timestamp = Carbon::parse($log['timestamp'])->setTimezone(config('app.timezone'));

            return [
                'workspace_id' => $workspace->id,
                'user_id' => $request->input('user_id'),
                'phone_number' => $log['phone_number'],
                'type' => $log['type'],
                'duration' => $log['duration'],
                'call_date' => $timestamp->toDateString(),
                'call_time' => $timestamp->toTimeString(),
                'created_at' => $now,
                'updated_at' => $now,
            ];

        }, $request->input('call_logs'));

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

        $date = $request->input('date', now()->toDateString());

        $deliveries = OrderForDelivery::where('workspace_id', $workspace->id)
            ->where('assignee_id', $request->input('user_id'))
            ->whereDate('delivery_date', $date)
            ->withCount(['customerCallLogs', 'riderCallLogs'])
            ->get();

        $totalOrders = $deliveries->count();
        $customersCalled = $deliveries->where('customer_call_logs_count', '>', 0)->count();
        $ridersCalled = $deliveries->where('rider_call_logs_count', '>', 0)->count();
        $totalCalled = $deliveries->filter(fn ($d) => $d->customer_call_logs_count > 0 || $d->rider_call_logs_count > 0)->count();
        $totalAttempts = $deliveries->sum('customer_call_logs_count') + $deliveries->sum('rider_call_logs_count');

        $totalTalkTime = CallLog::where('workspace_id', $workspace->id)
            ->where('user_id', $request->input('user_id'))
            ->whereDate('call_date', $date)
            ->whereExists(function ($query) use ($workspace, $date) {
                $query->from('pancake_order_for_delivery')
                    ->where('pancake_order_for_delivery.workspace_id', $workspace->id)
                    ->whereDate('pancake_order_for_delivery.delivery_date', $date)
                    ->whereRaw('(pancake_order_for_delivery.customer_phone = call_logs.phone_number OR pancake_order_for_delivery.rider_phone = call_logs.phone_number)');
            })
            ->sum('duration');

        return response()->json([
            'total_called' => $totalCalled,
            'total_orders' => $totalOrders,
            'riders_called' => $ridersCalled,
            'customers_called' => $customersCalled,
            'total_attempts' => $totalAttempts,
            'total_talk_time' => (int) $totalTalkTime,
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'uuid'],
            'since' => ['nullable'],
            'until' => ['nullable'],
        ]);

        $workspace = $request->attributes->get('workspace');

        $query = CallLog::where('workspace_id', $workspace->id)
            ->where('user_id', $request->input('user_id'));

        if ($since = $request->input('since')) {
            $sinceCarbon = is_numeric($since)
                ? Carbon::createFromTimestampMs((int) $since)
                : Carbon::parse($since);
            $query->whereRaw("CONCAT(call_date, ' ', call_time) >= ?", [$sinceCarbon->format('Y-m-d H:i:s')]);
        }

        if ($until = $request->input('until')) {
            $untilCarbon = is_numeric($until)
                ? Carbon::createFromTimestampMs((int) $until)
                : Carbon::parse($until);
            $query->whereRaw("CONCAT(call_date, ' ', call_time) <= ?", [$untilCarbon->format('Y-m-d H:i:s')]);
        }

        $rows = $query->orderByDesc('call_date')
            ->orderByDesc('call_time')
            ->limit(2000)
            ->get(['phone_number', 'type', 'duration', 'call_date', 'call_time'])
            ->map(fn ($r) => [
                'phone_number' => $r->phone_number,
                'type' => $r->type,
                'duration' => (int) $r->duration,
                'call_date' => $r->call_date instanceof \Carbon\CarbonInterface ? $r->call_date->toDateString() : (string) $r->call_date,
                'call_time' => (string) $r->call_time,
            ]);

        return response()->json([
            'data' => $rows,
            'total' => $rows->count(),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'uuid'],
            'since' => ['nullable'],
        ]);

        $workspace = $request->attributes->get('workspace');

        $query = CallLog::where('workspace_id', $workspace->id)
            ->where('user_id', $request->input('user_id'));

        if ($since = $request->input('since')) {
            $sinceCarbon = is_numeric($since)
                ? Carbon::createFromTimestampMs((int) $since)
                : Carbon::parse($since);
            $query->whereRaw("CONCAT(call_date, ' ', call_time) >= ?", [$sinceCarbon->format('Y-m-d H:i:s')]);
        }

        $numbers = $query->selectRaw('phone_number, COUNT(*) as count, SUM(duration) as total_duration, MAX(CONCAT(call_date, " ", call_time)) as last_called_at')
            ->groupBy('phone_number')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => [
                'phone_number' => $r->phone_number,
                'count' => (int) $r->count,
                'total_duration' => (int) $r->total_duration,
                'last_called_at' => $r->last_called_at,
            ]);

        return response()->json([
            'numbers' => $numbers,
            'total_calls' => $numbers->sum('count'),
            'total_duration' => $numbers->sum('total_duration'),
        ]);
    }
}
