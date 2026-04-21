<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
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

        $deliveries = OrderForDelivery::where('workspace_id', $workspace->id)
            ->whereDate('delivery_date', now())
            ->with('order.shippingAddress')
            ->get();

        // Build lookup maps: phone -> list of delivery records
        $customerMap = [];
        $riderMap = [];

        foreach ($deliveries as $delivery) {
            $customerPhone = $delivery->order?->shippingAddress?->phone_number;
            if ($customerPhone) {
                $customerMap[$customerPhone][] = $delivery;
            }

            $riderPhone = $delivery->rider_phone;
            if ($riderPhone) {
                $riderMap[$riderPhone][] = $delivery;
            }
        }

        $matched = 0;
        $unmatched = 0;

        foreach ($request->input('call_logs') as $log) {
            $phone = $log['phone_number'];
            $duration = $log['duration'];
            $calledAt = Carbon::parse($log['timestamp']);

            // Match against customer phones
            if (isset($customerMap[$phone])) {
                foreach ($customerMap[$phone] as $delivery) {
                    $delivery->increment('customer_call_attempts');
                    $delivery->increment('customer_call_duration', $duration);
                    $delivery->update(['customer_last_call' => $calledAt]);
                }
                $matched++;

                continue;
            }

            // Match against rider phones
            if (isset($riderMap[$phone])) {
                foreach ($riderMap[$phone] as $delivery) {
                    $delivery->increment('rider_call_attempts');
                    $delivery->increment('rider_call_duration', $duration);
                    $delivery->update(['rider_last_call' => $calledAt]);
                }
                $matched++;

                continue;
            }

            $unmatched++;
        }

        return response()->json([
            'matched' => $matched,
            'unmatched' => $unmatched,
            'total' => count($request->input('call_logs')),
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
            ->get();

        $totalCalled = $deliveries->filter(fn ($d) => $d->customer_call_attempts > 0 || $d->rider_call_attempts > 0)->count();
        $totalOrders = $deliveries->count();
        $ridersCalled = $deliveries->filter(fn ($d) => $d->rider_call_attempts > 0)->count();
        $customersCalled = $deliveries->filter(fn ($d) => $d->customer_call_attempts > 0)->count();
        $totalTime = $deliveries->sum('customer_call_duration') + $deliveries->sum('rider_call_duration');
        $totalAttempts = $deliveries->sum('customer_call_attempts') + $deliveries->sum('rider_call_attempts');

        return response()->json([
            'total_called' => $totalCalled,
            'total_orders' => $totalOrders,
            'riders_called' => $ridersCalled,
            'customers_called' => $customersCalled,
            'total_time' => $totalTime,
            'total_attempts' => $totalAttempts,
        ]);
    }
}
