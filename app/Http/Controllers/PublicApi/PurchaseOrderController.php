<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Models\PurchasedOrderItem;
use Modules\Inventory\Models\PurchasedOrderItemDelivery;

class PurchaseOrderController extends Controller
{
    /**
     * Receive ERP purchase orders synced back by n8n in bulk.
     *
     * The body is a bare JSON array of purchase orders (or an { "orders": [...] }
     * wrapper). Each PO already carries local field names, a numeric status code
     * and its own item lines + deliveries:
     *
     * [
     *   {
     *     "control_no": "CN-TP693",          // unique key per workspace (upsert key)
     *     "issue_date": "2026-04-15",        // Y-m-d
     *     "delivery_no": "DN-TP693",
     *     "cust_po_no": "CPO-TP693",
     *     "delivery_fee": 0,
     *     "total_amount": 230400,
     *     "status": 7,                       // PurchasedOrder::STATUSES code (1-8)
     *     "items": [
     *       { "inventory_item_id": 2, "count": 1280, "amount": 180, "total_amount": 230400 }
     *     ],
     *     "deliveries": [
     *       { "qty": 1279, "created_at": "2026-06-17 16:47:31" },
     *       { "qty": 1, "created_at": "2026-06-18 12:08:25" }
     *     ]
     *   }
     * ]
     *
     * Deliveries are PO-level and attach to the PO's item line (these ERP POs
     * carry a single inventory item). They are replaced wholesale on each sync so
     * delivered quantities always reflect the ERP. Inventory items not owned by
     * the authenticated workspace are skipped.
     */
    public function bulkSync(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $payload = $request->json()->all();

        if (empty($payload)) {
            $payload = $request->all();
        }

        // Accept a bare array body or an { orders|items|data: [...] } wrapper.
        $orders = array_is_list($payload)
            ? $payload
            : ($payload['orders'] ?? $payload['items'] ?? $payload['data'] ?? []);

        // Resolve every referenced inventory item once, scoped to the workspace.
        $itemIds = collect($orders)
            ->flatMap(fn ($po) => collect($po['items'] ?? [])->pluck('inventory_item_id'))
            ->filter()
            ->unique()
            ->all();

        $itemsById = InventoryItem::where('workspace_id', $workspace->id)
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy('id');

        $results = [];

        // PO lines synced per inventory item, used to resolve each item's sync run.
        $perItem = [];

        DB::transaction(function () use ($orders, $workspace, $itemsById, &$results, &$perItem) {
            foreach ($orders as $po) {
                $controlNo = $po['control_no'] ?? null;

                if (! $controlNo) {
                    $results[] = ['control_no' => null, 'status' => 'skipped', 'reason' => 'missing control_no'];

                    continue;
                }

                $order = PurchasedOrder::updateOrCreate(
                    [
                        'workspace_id' => $workspace->id,
                        'control_no' => $controlNo,
                    ],
                    [
                        'issue_date' => $this->toDate($po['issue_date'] ?? null),
                        'delivery_no' => $po['delivery_no'] ?? null,
                        'cust_po_no' => $po['cust_po_no'] ?? null,
                        'delivery_fee' => $po['delivery_fee'] ?? 0,
                        'total_amount' => $po['total_amount'] ?? 0,
                        'status' => $this->normalizeStatus($po['status'] ?? null),
                    ]
                );

                // Upsert each item line, skipping ids that aren't in this workspace.
                $lineIds = [];

                foreach ($po['items'] ?? [] as $line) {
                    $itemId = $line['inventory_item_id'] ?? null;
                    $item = $itemId ? $itemsById->get($itemId) : null;

                    if (! $item) {
                        continue;
                    }

                    $orderItem = PurchasedOrderItem::updateOrCreate(
                        [
                            'inventory_purchased_order_id' => $order->id,
                            'inventory_item_id' => $item->id,
                        ],
                        [
                            'count' => (int) ($line['count'] ?? 0),
                            'amount' => $line['amount'] ?? 0,
                            'total_amount' => $line['total_amount'] ?? 0,
                        ]
                    );

                    $lineIds[] = $orderItem->id;

                    // Tally PO lines per item and remember the sync run id n8n
                    // echoed back (on the line, else on the PO) for an exact match.
                    $perItem[$item->id]['lines'] = ($perItem[$item->id]['lines'] ?? 0) + 1;
                    $perItem[$item->id]['sync_run_id'] ??= $this->syncRunId($line) ?? $this->syncRunId($po);
                }

                // Deliveries are PO-level; attach them to the PO's (single) item
                // line and replace wholesale so delivered quantities track the ERP.
                $deliveriesSynced = 0;
                $primaryLineId = $lineIds[0] ?? null;

                if ($primaryLineId !== null && array_key_exists('deliveries', $po)) {
                    PurchasedOrderItemDelivery::where('inventory_purchased_order_item_id', $primaryLineId)->delete();

                    foreach ($po['deliveries'] ?? [] as $delivery) {
                        PurchasedOrderItemDelivery::create([
                            'inventory_purchased_order_item_id' => $primaryLineId,
                            'delivery_date' => $this->toDate($delivery['delivery_date'] ?? $delivery['created_at'] ?? null)
                                ?? $this->toDate($po['issue_date'] ?? null)
                                ?? now()->toDateString(),
                            'delivery_no' => $delivery['delivery_no'] ?? $po['delivery_no'] ?? null,
                            'qty' => (int) ($delivery['qty'] ?? 0),
                        ]);

                        $deliveriesSynced++;
                    }
                }

                $results[] = [
                    'control_no' => $controlNo,
                    'purchased_order_id' => $order->id,
                    'items_synced' => count($lineIds),
                    'deliveries_synced' => $deliveriesSynced,
                    'status' => 'synced',
                ];
            }
        });

        // Resolve each item's outstanding PO sync run now that the data is committed.
        foreach ($perItem as $itemId => $info) {
            $this->recordSyncRun($workspace->id, $info['sync_run_id'] ?? null, (int) $itemId, $info['lines']);
        }

        return response()->json(['data' => $results]);
    }

    /** Pull the sync run id n8n echoed back, tolerating a couple of key spellings. */
    private function syncRunId(array $source): ?int
    {
        $id = $source['sync_run_id'] ?? $source['syncRunId'] ?? null;

        return ($id === null || $id === '') ? null : (int) $id;
    }

    /**
     * Resolve the item's purchase-order sync run as successful. We match the exact
     * run id n8n echoed back when present, otherwise the item's latest pending
     * run. If neither exists (a manual or replayed callback), record a fresh
     * resolved run so the sync still shows up in the Sync Health view. `$lines`
     * is the number of PO line items synced for this inventory item.
     */
    private function recordSyncRun(int $workspaceId, ?int $syncRunId, int $itemId, int $lines): void
    {
        $run = GencysSyncRun::resolveFor($workspaceId, $syncRunId, $itemId, GencysSyncRun::TYPE_PURCHASE_ORDER);

        if ($run) {
            $run->succeed($lines, $lines);

            return;
        }

        GencysSyncRun::create([
            'workspace_id' => $workspaceId,
            'inventory_item_id' => $itemId,
            'sync_type' => GencysSyncRun::TYPE_PURCHASE_ORDER,
            'status' => GencysSyncRun::STATUS_SUCCESS,
            'rows_received' => $lines,
            'rows_saved' => $lines,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    /** Parse any date/datetime string into Y-m-d, or null when empty/unparseable. */
    private function toDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Resolve an already-mapped numeric status to a local code, keeping it when
     * it's a known status and defaulting to 1 (For Approval) otherwise.
     */
    private function normalizeStatus(mixed $status): int
    {
        $code = (int) $status;

        return array_key_exists($code, PurchasedOrder::STATUSES) ? $code : 1;
    }
}
