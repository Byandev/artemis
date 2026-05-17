<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Pancake\Models\ReturnedOrder;

class ShopScanReturnController extends Controller
{
    public function scan(Request $request): JsonResponse
    {
        $request->validate([
            'shop_id' => ['required', 'integer'],
            'tracking_code' => ['required', 'string'],
        ]);

        $workspace = $request->attributes->get('workspace');

        $order = Order::where('workspace_id', $workspace->id)
            ->where('shop_id', $request->input('shop_id'))
            ->where('tracking_code', $request->input('tracking_code'))
            ->first();

        if (! $order) {
            return response()->json([
                'error' => 'Order not found.',
            ], 404);
        }

        if ($order->parcel_status !== 'returned') {

            // Update order status
            $order->update([
                'parcel_status' => 'returned',
                'returned_at' => now(),
            ]);

            // Store returned order record
            ReturnedOrder::firstOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'order_id' => $order->id,
                ],
                [
                    'shop_id' => $order->shop_id,
                    'tracking_code' => $order->tracking_code,
                    'order_number' => $order->order_number,
                    'scanned_at' => now(),
                ]
            );

            return response()->json([
                'message' => 'Order marked as returned.',
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'tracking_code' => $order->tracking_code,
                    'parcel_status' => $order->parcel_status,
                    'returned_at' => $order->returned_at,
                ],
            ]);
        }

        return response()->json([
            'error' => 'Order is already marked as returned.',
        ], 422);
    }
}
