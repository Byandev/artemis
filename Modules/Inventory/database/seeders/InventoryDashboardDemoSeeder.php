<?php

namespace Modules\Inventory\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\InventoryItem;

/**
 * Dev-only demo data for the inventory dashboard: ~30 days of stock movement,
 * purchase orders (with deliveries) and physical-count discrepancies, so every
 * widget renders with realistic shapes AND visibly responds to the date-range
 * filter. Never runs in production. Idempotent — re-running clears prior demo
 * rows first.
 *
 *   php artisan db:seed --class="Modules\Inventory\Database\Seeders\InventoryDashboardDemoSeeder"
 */
class InventoryDashboardDemoSeeder extends Seeder
{
    private CarbonImmutable $start;

    private CarbonImmutable $end;

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Skipped: demo seeder does not run in production.');

            return;
        }

        $workspaceId = InventoryItem::query()
            ->selectRaw('workspace_id')
            ->groupBy('workspace_id')
            ->orderByRaw('COUNT(*) DESC')
            ->value('workspace_id');

        if (! $workspaceId) {
            $this->command?->warn('No inventory items found to seed data for.');

            return;
        }

        $items = InventoryItem::where('workspace_id', $workspaceId)
            ->inRandomOrder()
            ->limit(20)
            ->pluck('id');

        $this->end = CarbonImmutable::now()->startOfDay();
        $this->start = $this->end->subDays(29);

        $this->clearDemoData($workspaceId);
        $tx = $this->seedTransactions($items, $workspaceId);
        $po = $this->seedPurchaseOrders($items, $workspaceId);
        $disc = $this->seedDiscrepancies($items, $workspaceId);

        $this->command?->info("Seeded {$tx} transactions, {$po} purchase orders and {$disc} discrepancies for workspace {$workspaceId}.");
    }

    /** Remove any data a previous run of this seeder inserted (keeps it idempotent). */
    private function clearDemoData(int $workspaceId): void
    {
        DB::table('inventory_transactions')->where('ref_no', 'like', 'DEMO-%')->delete();

        $demoPoIds = DB::table('inventory_purchased_orders')
            ->where('workspace_id', $workspaceId)
            ->where('control_no', 'like', 'DEMO-%')
            ->pluck('id');

        if ($demoPoIds->isNotEmpty()) {
            $itemIds = DB::table('inventory_purchased_order_items')
                ->whereIn('inventory_purchased_order_id', $demoPoIds)
                ->pluck('id');
            DB::table('inventory_purchased_order_item_deliveries')->whereIn('inventory_purchased_order_item_id', $itemIds)->delete();
            DB::table('inventory_purchased_order_items')->whereIn('inventory_purchased_order_id', $demoPoIds)->delete();
            DB::table('inventory_purchased_orders')->whereIn('id', $demoPoIds)->delete();
        }

        // Discrepancies carry no marker column; on local demo, clear the window.
        DB::table('inventory_item_discrepancies')
            ->where('workspace_id', $workspaceId)
            ->whereBetween('date', [$this->start->toDateString(), $this->end->toDateString()])
            ->delete();
    }

    private function seedTransactions(Collection $itemIds, int $workspaceId): int
    {
        $rows = [];

        foreach ($itemIds as $itemId) {
            $remaining = mt_rand(40, 200);

            for ($date = $this->start; $date->lte($this->end); $date = $date->addDay()) {
                if (mt_rand(1, 100) <= 35) {
                    continue; // organic gaps
                }

                $poIn = mt_rand(0, 100) <= 30 ? mt_rand(10, 60) : 0;
                $rtsIn = mt_rand(0, 100) <= 25 ? mt_rand(1, 12) : 0;
                $poOut = mt_rand(0, 100) <= 55 ? mt_rand(1, 25) : 0;
                $rtsOut = mt_rand(0, 100) <= 20 ? mt_rand(1, 8) : 0;
                $bad = mt_rand(0, 100) <= 12 ? mt_rand(1, 4) : 0;
                $lost = mt_rand(0, 100) <= 8 ? mt_rand(1, 3) : 0;

                $remaining = max(0, $remaining + $poIn + $rtsIn - $poOut - $rtsOut - $bad - $lost);

                $rows[] = [
                    'workspace_id' => $workspaceId,
                    'inventory_item_id' => $itemId,
                    'date' => $date->toDateString(),
                    'ref_no' => 'DEMO-'.$itemId.'-'.$date->format('md'),
                    'po_qty_in' => $poIn,
                    'po_qty_out' => $poOut,
                    'rts_goods_in' => $rtsIn,
                    'rts_goods_out' => $rtsOut,
                    'rts_bad' => $bad,
                    'lost' => $lost,
                    'remaining_qty' => $remaining,
                    'inventory_remaining_stock' => $remaining,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        collect($rows)->chunk(500)->each(fn ($c) => DB::table('inventory_transactions')->insert($c->all()));

        return count($rows);
    }

    private function seedPurchaseOrders(Collection $itemIds, int $workspaceId): int
    {
        $count = 0;

        for ($i = 1; $i <= 14; $i++) {
            $issue = $this->start->addDays(mt_rand(0, 29));
            $status = mt_rand(1, 8);
            // Expected date straddles today so some read "delayed", some "upcoming".
            $expected = $issue->addDays(mt_rand(3, 25));

            $poId = DB::table('inventory_purchased_orders')->insertGetId([
                'workspace_id' => $workspaceId,
                'issue_date' => $issue->toDateString(),
                'delivery_no' => 'DN-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'expected_delivery_date' => $expected->toDateString(),
                'cust_po_no' => 'PO-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'control_no' => 'DEMO-'.$i,
                'delivery_fee' => mt_rand(0, 500),
                'total_amount' => 0,
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $lineItems = $itemIds->shuffle()->take(mt_rand(2, 4));
            $poTotal = 0;

            foreach ($lineItems as $itemId) {
                $qty = mt_rand(10, 80);
                $amount = mt_rand(20, 300);
                $lineTotal = $qty * $amount;
                $poTotal += $lineTotal;

                $poiId = DB::table('inventory_purchased_order_items')->insertGetId([
                    'inventory_purchased_order_id' => $poId,
                    'inventory_item_id' => $itemId,
                    'count' => $qty,
                    'amount' => $amount,
                    'total_amount' => $lineTotal,
                    'remarks' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Mix of waiting / partial / fully delivered to fill the donut.
                $roll = mt_rand(1, 100);
                $delivered = $roll <= 33 ? 0 : ($roll <= 66 ? (int) floor($qty / 2) : $qty);

                if ($delivered > 0) {
                    DB::table('inventory_purchased_order_item_deliveries')->insert([
                        'inventory_purchased_order_item_id' => $poiId,
                        'delivery_date' => $issue->addDays(mt_rand(1, 10))->toDateString(),
                        'delivery_no' => 'DN-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                        'qty' => $delivered,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('inventory_purchased_orders')->where('id', $poId)->update(['total_amount' => $poTotal]);
            $count++;
        }

        return $count;
    }

    private function seedDiscrepancies(Collection $itemIds, int $workspaceId): int
    {
        $rows = [];

        for ($i = 0; $i < 22; $i++) {
            $counted = mt_rand(0, 200);
            // Weighted toward small variances, with the occasional large one.
            $discrepancy = mt_rand(1, 100) <= 25 ? mt_rand(-18, 18) : mt_rand(-6, 6);

            $rows[] = [
                'workspace_id' => $workspaceId,
                'inventory_item_id' => $itemIds->random(),
                'date' => $this->start->addDays(mt_rand(0, 29))->toDateString(),
                'counted_qty' => $counted,
                'discrepancy' => $discrepancy,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('inventory_item_discrepancies')->insert($rows);

        return count($rows);
    }
}
