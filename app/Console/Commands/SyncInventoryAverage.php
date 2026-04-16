<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\InventoryItem;

class SyncInventoryAverage extends Command
{
    protected $signature = 'inventory:sync-averages';
    protected $description = 'Update three_days_average and unfulfilled_count on inventory items for workspaces with inventory_sync enabled';

    public function handle(): void
    {
        $workspaceIds = Workspace::where('inventory_sync', true)->pluck('id');

        if ($workspaceIds->isEmpty()) {
            $this->info('No workspaces have inventory_sync enabled.');
            return;
        }

        // Last 3 full days, excluding today.
        // e.g. if today is Apr 13, range is Apr 10 00:00:00 → Apr 13 00:00:00 (exclusive)
        $start = now()->subDays(3)->startOfDay();
        $end   = now()->startOfDay();

        $items = InventoryItem::whereIn('workspace_id', $workspaceIds)->get(['id', 'product_id']);

        foreach ($items as $item) {
            // 3-day average: sum of item quantities on confirmed orders in the last 3 full days
            $totalQty = DB::table('pancake_order_items')
                ->join('pancake_orders', 'pancake_orders.id', '=', 'pancake_order_items.order_id')
                ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
                ->where('pages.product_id', $item->product_id)
                ->whereNotNull('pancake_orders.confirmed_at')
                ->whereBetween('pancake_orders.confirmed_at', [$start, $end])
                ->sum('pancake_order_items.quantity');

            $average = round($totalQty / 3, 4);

            // Unfulfilled count: sum of item quantities on orders with status in 1, 8, 9
            $unfulfilled = DB::table('pancake_order_items')
                ->join('pancake_orders', 'pancake_orders.id', '=', 'pancake_order_items.order_id')
                ->join('pages', 'pages.id', '=', 'pancake_orders.page_id')
                ->where('pages.product_id', $item->product_id)
                ->whereIn('pancake_orders.status', [1, 8, 9])
                ->sum('pancake_order_items.quantity');

            InventoryItem::where('id', $item->id)->update([
                'three_days_average' => $average,
                'unfulfilled_count'  => $unfulfilled,
            ]);
        }

        $this->info("Synced {$items->count()} inventory items across {$workspaceIds->count()} workspace(s).");
    }
}
