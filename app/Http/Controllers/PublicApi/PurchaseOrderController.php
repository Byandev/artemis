<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Support\BatchRunner;
use Modules\GencysERP\Support\SyncCallbackFields;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Models\PurchasedOrderItem;
use Modules\Inventory\Models\PurchasedOrderItemDelivery;
use Modules\Inventory\Models\PurchasedOrderStatusLog;

class PurchaseOrderController extends Controller
{
    /**
     * Receive ERP purchase orders synced back by n8n, grouped per inventory item.
     * Each data[] entry echoes the item id and the sync_run_id we sent, and lists
     * that item's purchase orders:
     *
     * {
     *   "data": [
     *     {
     *       "id": 55,                     // inventory item id
     *       "sync_run_id": 2,             // the run we opened on dispatch
     *       "purchased_orders": [
     *         {
     *           "control_no": "CN-TP839", // unique key per workspace (upsert key)
     *           "issue_date": "2026-06-25",
     *           "delivery_no": "DN-TP839",
     *           "cust_po_no": "CPO-TP839",
     *           "delivery_fee": 0,
     *           "total_amount": 42752,
     *           "status": 7,              // PurchasedOrder::STATUSES code (1-8)
     *           "supplier": "BFM",        // free-text name, no supplier table
     *           "items": [ { "count": 800, "amount": 57.23, "total_amount": 45784 } ],
     *           "deliveries": [ { "qty": 800, "created_at": "2026-06-29 11:07:02" } ],
     *           "statusLogs": [           // the ERP's audit trail, any order
     *             { "status": "Paid", "by": "RENZ LAICA MERCADO",
     *               "detail": "PAID-50%", "timestamp": "2026-07-24 16:23:30" }
     *           ],
     *           // When the order reached each stage. Null until it gets there.
     *           "approved_at": "2026-07-23 17:32:32",
     *           "to_pay_at": "2026-07-23 17:33:52",
     *           "paid_at": "2026-07-24 16:23:30",
     *           "for_purchase_at": "2026-07-24 16:52:25",
     *           "purchased_at": "2026-07-24 16:52:34"
     *         }
     *       ]
     *     }
     *   ]
     * }
     *
     * These ERP POs carry a single inventory item, so each PO's line is attached
     * to the entry's item. Deliveries and status logs are replaced wholesale each
     * sync. Items not owned by the authenticated workspace are skipped.
     */
    public function bulkSync(Request $request, BatchRunner $runner): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $entries = $request->input('data', []);

        $itemsById = InventoryItem::where('workspace_id', $workspace->id)
            ->whereIn('id', collect($entries)->pluck('id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        $results = [];

        DB::transaction(function () use ($entries, $workspace, $itemsById, $request, &$results) {
            foreach ($entries as $entry) {
                $item = $itemsById->get($entry['id'] ?? null);

                $synced = 0;
                foreach (($entry['purchased_orders'] ?? []) as $po) {
                    if ($item && $this->saveOrder($workspace, $item, $po)) {
                        $synced++;
                    }
                }

                // The entry's sync_run_id is the run we opened for this item.
                GencysSyncRun::succeedById(
                    $workspace->id,
                    SyncCallbackFields::runId($entry, $request),
                    $synced,
                    executionId: SyncCallbackFields::executionId($entry, $request),
                );

                $results[] = [
                    'inventory_item_id' => $entry['id'] ?? null,
                    'orders_synced' => $synced,
                ];
            }
        });

        // Every run this callback covered is now resolved, so whichever batch
        // they belonged to can send its next group.
        $runner->tick();

        return response()->json(['data' => $results]);
    }

    /**
     * Upsert one purchase order (header, its single item line, deliveries and
     * the ERP's status trail) for the given inventory item. Returns false when
     * the PO has no control_no.
     */
    private function saveOrder(Workspace $workspace, InventoryItem $item, array $po): bool
    {
        $controlNo = $po['control_no'] ?? null;

        if (! $controlNo) {
            return false;
        }

        $order = PurchasedOrder::updateOrCreate(
            ['workspace_id' => $workspace->id, 'control_no' => $controlNo],
            [
                'issue_date' => $this->toDate($po['issue_date'] ?? null),
                // The ERP sends this on barely any order, so the standing
                // two-week agreement fills it in at sync time rather than every
                // reader guessing the same fallback for itself.
                'expected_delivery_date' => PurchasedOrder::expectedDeliveryFor(
                    $this->toDate($po['expected_delivery_date'] ?? null),
                    $this->toDate($po['issue_date'] ?? null),
                ),
                'delivery_no' => $po['delivery_no'] ?? null,
                'cust_po_no' => $po['cust_po_no'] ?? null,
                'supplier' => $this->trimmed($po['supplier'] ?? null),
                'delivery_fee' => $po['delivery_fee'] ?? 0,
                'total_amount' => $po['total_amount'] ?? 0,
                'status' => $this->normalizeStatus($po['status'] ?? null),
                ...$this->stageTimestamps($po),
            ]
        );

        $this->saveStatusLogs($order, $po);

        $line = $po['items'][0] ?? [];

        $orderItem = PurchasedOrderItem::updateOrCreate(
            ['inventory_purchased_order_id' => $order->id, 'inventory_item_id' => $item->id],
            [
                'count' => (int) ($line['count'] ?? 0),
                'amount' => $line['amount'] ?? 0,
                'total_amount' => $line['total_amount'] ?? 0,
            ]
        );

        // Replace deliveries wholesale so delivered quantities track the ERP.
        if (array_key_exists('deliveries', $po)) {
            PurchasedOrderItemDelivery::where('inventory_purchased_order_item_id', $orderItem->id)->delete();

            foreach (($po['deliveries'] ?? []) as $delivery) {
                PurchasedOrderItemDelivery::create([
                    'inventory_purchased_order_item_id' => $orderItem->id,
                    'delivery_date' => $this->toDate($delivery['delivery_date'] ?? $delivery['created_at'] ?? null)
                        ?? $this->toDate($po['issue_date'] ?? null)
                        ?? now()->toDateString(),
                    'delivery_no' => $delivery['delivery_no'] ?? $po['delivery_no'] ?? null,
                    'qty' => (int) ($delivery['qty'] ?? 0),
                ]);
            }
        }

        return true;
    }

    /**
     * The stage timestamps the ERP sends alongside the trail, as columns to
     * write.
     *
     * Only keys actually present are returned: a field the ERP has stopped
     * sending must leave the stored value alone, while one sent as null is the
     * ERP saying the order has not reached that stage (or has been moved back),
     * and does clear it.
     *
     * @return array<string, Carbon|null>
     */
    private function stageTimestamps(array $po): array
    {
        $stamps = [];

        foreach (PurchasedOrder::STAGE_TIMESTAMPS as $field) {
            if (array_key_exists($field, $po)) {
                $stamps[$field] = $this->toDateTime($po[$field]);
            }
        }

        return $stamps;
    }

    /**
     * Replace the order's status trail with what the ERP sent, and — only when
     * the ERP did not send `paid_at` itself — denormalise the payment date out
     * of it onto the order.
     *
     * Wholesale replacement, like deliveries: the ERP owns this trail and can
     * revise it, and there is no local id to match entries on. A payload that
     * omits `statusLogs` entirely leaves both the trail and paid_at untouched —
     * absent means "not sent", not "cleared".
     */
    private function saveStatusLogs(PurchasedOrder $order, array $po): void
    {
        if (! array_key_exists('statusLogs', $po)) {
            return;
        }

        // Bypasses model events on purpose: a wholesale replace would otherwise
        // re-derive paid_at once per row. The single recalculate below does the
        // same work once.
        PurchasedOrderStatusLog::where('inventory_purchased_order_id', $order->id)->toBase()->delete();

        $now = now();
        $rows = [];

        foreach (($po['statusLogs'] ?? []) as $log) {
            $status = $this->trimmed($log['status'] ?? null);

            if ($status === null) {
                continue;
            }

            $rows[] = [
                'inventory_purchased_order_id' => $order->id,
                'status' => $status,
                'by' => $this->trimmed($log['by'] ?? null),
                'detail' => $log['detail'] ?? null,
                'logged_at' => $this->toDateTime($log['timestamp'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows) {
            PurchasedOrderStatusLog::insert($rows);
        }

        // The ERP's own paid_at is authoritative when it sends one — re-deriving
        // would overwrite it with a reading of the same trail it came from, and
        // disagree whenever the ERP knows something the log does not.
        if (array_key_exists('paid_at', $po)) {
            return;
        }

        // Runs even when the trail came back empty, so an order whose Paid entry
        // the ERP has withdrawn stops reporting a payment date.
        $order->recalculatePaidAt();
    }

    /** Trim a scalar to a non-empty string, or null. */
    private function trimmed(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /** Parse any date/datetime string into a Carbon instance, or null. */
    private function toDateTime(?string $value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
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
