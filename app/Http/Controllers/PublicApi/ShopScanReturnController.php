<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ScannedReturnedOrder;
use App\Models\Shop;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Pancake\Services\Pancake;

class ShopScanReturnController extends Controller
{
    /** Pancake POS order status code for "returned". */
    private const PANCAKE_STATUS_RETURNED = 5;

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
            return response()->json(['error' => 'Order not found.'], 404);
        }

        if ($order->parcel_status === 'returned') {
            return response()->json(['error' => 'Order is already marked as returned.'], 422);
        }

        // Logged before Pancake is called, so a scan that fails to sync still
        // shows up (synced_to_pancake = false) to retry. Scanning the same
        // order again after a failure reuses its unsynced row.
        $scan = ScannedReturnedOrder::firstOrNew([
            'workspace_id' => $workspace->id,
            'order_id' => $order->id,
            'synced_to_pancake' => false,
        ]);
        $scan->fill([
            'shop_id' => $order->shop_id,
            'order_number' => $order->order_number,
            'tracking_code' => $order->tracking_code,
            'scanned_at' => now(),
        ])->save();

        $shop = Shop::where('workspace_id', $workspace->id)->find($order->shop_id);

        if (! $shop?->pos_token) {
            return response()->json(['error' => 'Shop has no Pancake POS token configured.'], 422);
        }

        // Pancake is the source of truth — only mark locally once POS accepted
        // the change, otherwise the next order sync would revert it anyway.
        try {
            $result = (new Pancake($shop->id, $shop->pos_token))
                ->updateOrderStatus((string) $order->order_number, self::PANCAKE_STATUS_RETURNED);
        } catch (RequestException|ConnectionException $e) {
            Log::warning('Pancake scan-return update failed', [
                'order_id' => $order->id,
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to update the order in Pancake POS.'], 502);
        }

        if (($result['success'] ?? true) === false) {
            Log::warning('Pancake scan-return update rejected', [
                'order_id' => $order->id,
                'shop_id' => $shop->id,
                'response' => $result,
            ]);

            return response()->json([
                'error' => 'Pancake POS rejected the update.',
                'pancake_message' => $result['message'] ?? null,
            ], 502);
        }

        DB::transaction(function () use ($order, $scan) {
            $order->update([
                'status' => self::PANCAKE_STATUS_RETURNED,
                'status_name' => 'returned',
                'parcel_status' => 'returned',
                'returned_at' => now(),
            ]);

            $scan->update(['synced_to_pancake' => true]);
        });

        return response()->json([
            'message' => 'Order marked as returned.',
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'tracking_code' => $order->tracking_code,
                'parcel_status' => 'returned',
            ],
        ]);
    }
}
