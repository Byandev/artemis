<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\Models\InventoryItem;

class InventoryItemController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $validated = $request->validate([
            'inventory_item_id' => ['required', 'integer'],
            'unfulfilled_count' => ['required', 'integer', 'min:0'],
            'three_days_average' => ['required', 'numeric', 'min:0'],
            'remaining_qty' => ['sometimes', 'integer', 'min:0'],
        ]);

        $item = InventoryItem::where('workspace_id', $workspace->id)
            ->where('id', $validated['inventory_item_id'])
            ->firstOrFail();

        $item->update([
            'unfulfilled_count' => $validated['unfulfilled_count'],
            'three_days_average' => $validated['three_days_average'],
            'remaining_qty' => $validated['remaining_qty'] ?? $item->remaining_qty,
        ]);

        return response()->json([
            'data' => $item,
        ]);
    }
}
