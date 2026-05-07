<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Models\InventoryItem;

class InventoryItemController extends Controller
{
    public function keywords(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $items = InventoryItem::where('workspace_id', $workspace->id)
            ->whereNotNull('sales_keywords')
            ->where('sales_keywords', '!=', '')
            ->select(['id', 'sales_keywords', 'transaction_keywords'])
            ->get()
            ->map(fn ($item) => [
                'inventory_item_id' => $item->id,
                'sales_keywords' => $item->sales_keywords,
                'transaction_keywords' => $item->transaction_keywords,
            ]);

        return response()->json($items);
    }

    public function sync(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $validated = $request->validate([
            'inventory_item_id' => ['required', 'integer'],
            'total_orders' => ['required', 'integer', 'min:0'],
            'unfulfilled_count' => ['required', 'integer', 'min:0'],
            'three_days_average' => ['required', 'numeric', 'min:0'],
        ]);

        $item = InventoryItem::where('workspace_id', $workspace->id)
            ->where('id', $validated['inventory_item_id'])
            ->firstOrFail();

        $item->update([
            'unfulfilled_count' => $validated['unfulfilled_count'],
            'three_days_average' => $validated['three_days_average'],
        ]);

        return response()->json([
            'data' => $item,
        ]);
    }
}
