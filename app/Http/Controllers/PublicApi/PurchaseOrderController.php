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
use Modules\GencysERP\Support\BulkCallback;
use Modules\GencysERP\Support\SyncCallbackFields;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Models\PurchasedOrderItem;
use Modules\Inventory\Models\PurchasedOrderItemDelivery;
use Modules\Inventory\Models\PurchasedOrderStatusLog;
use Modules\Inventory\Support\InventoryItemResolver;

class PurchaseOrderController extends Controller
{
    /**
     * Receive one date range's ERP purchase orders, as n8n scrapes them: every
     * order on the list, one entry each.
     *
     * The body wraps the run id around the list n8n scraped:
     *
     * { "sync_run_id": 3196, "purchased_orders": [ ...the orders... ] }
     *
     * which may itself arrive inside an array, and the orders may equally
     * arrive on their own, bare or under a { data: [...] } / { items: [...] }
     * key. An order looks like this:
     *
     * [
     *   {
     *     "control_no": "CN-TP1010",   // unique key per workspace (upsert key)
     *     "issue_date": "2026-08-28",
     *     "delivery_no": "DN-TP1010",
     *     "cust_po_no": "CPO-TP1010",
     *     "delivery_fee": 750,
     *     "total_amount": 32750,
     *     "status": 6,                 // PurchasedOrder::STATUSES code (1-9)
     *     "supplier": "Kintara Manuf Ventures Inc",
     *     "items": [
     *       { "count": 800, "amount": 40, "total_amount": 32000,
     *         "item": "HIKARI PARAGIS THERAPY HEART CARE (Satellite)" }
     *     ],
     *     "statusLogs": [              // the ERP's audit trail, any order
     *       { "status": "Paid", "by": "RENZ LAICA MERCADO",
     *         "detail": "PAID-50%", "timestamp": "2026-08-28 17:28:18" }
     *     ],
     *     "deliveries": [ { "qty": 800, "created_at": "2026-08-29 11:07:02" } ]
     *   }
     * ]
     *
     * Line items arrive by name and are matched (or created) by
     * InventoryItemResolver, exactly as transaction history does. The stage
     * timestamps the ERP used to send alongside the trail are no longer in the
     * payload, so they are read back off the trail instead.
     *
     * The whole callback belongs to a single sync run — the range's run, whose
     * id n8n echoes back as `sync_run_id`, beside the list or at the top level.
     * See BulkCallback.
     */
    public function bulkSync(Request $request, BatchRunner $runner): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $entries = BulkCallback::entries($request, ['purchased_orders', 'data', 'items']);
        $resolver = new InventoryItemResolver($workspace->id);
        $executionId = SyncCallbackFields::executionId($request);

        $results = [];
        $received = 0;
        $saved = 0;

        // Orders tallied against the run that carried them, plus the ones that
        // named no run and fall back to the request.
        $tallies = [];
        $loose = ['received' => 0, 'saved' => 0];

        DB::transaction(function () use ($entries, $workspace, $resolver, &$results, &$received, &$saved, &$tallies, &$loose) {
            foreach ($entries as $entry) {
                $wrapped = is_array($entry['purchased_orders'] ?? null);

                // An entry is either a run's whole result or, when n8n posts
                // the list flat, a single order.
                $orders = $wrapped ? array_filter($entry['purchased_orders'], 'is_array') : [$entry];
                $runId = $wrapped ? SyncCallbackFields::runId($entry) : null;

                foreach ($orders as $po) {
                    $order = $this->saveOrder($workspace, $po, fn (array $line) => $resolver->resolve($line['item'] ?? null));

                    $results[] = $order
                        ? [
                            'control_no' => $order->control_no,
                            'purchased_order_id' => $order->id,
                            'lines' => $order->items()->count(),
                            'status' => 'synced',
                        ]
                        : [
                            'control_no' => $po['control_no'] ?? null,
                            'status' => 'skipped',
                            'reason' => 'no control_no to key the order on',
                        ];

                    $received++;
                    $saved += $order ? 1 : 0;

                    $tally = $runId ? ($tallies[$runId] ?? ['received' => 0, 'saved' => 0]) : $loose;
                    $tally['received']++;
                    $tally['saved'] += $order ? 1 : 0;

                    if ($runId) {
                        $tallies[$runId] = $tally;
                    } else {
                        $loose = $tally;
                    }
                }
            }
        });

        // A run n8n named is credited with exactly what it carried; a callback
        // that named none is credited to whichever run is in flight.
        if (! $tallies && $loose['received'] > 0) {
            $tallies[0] = $loose;
        }

        $credited = [];

        foreach ($tallies as $runId => $tally) {
            $run = BulkCallback::creditRun(
                $request,
                $workspace->id,
                GencysSyncRun::TYPE_PURCHASE_ORDER,
                $tally['received'],
                $tally['saved'],
                $executionId,
                $runId ?: null,
            );

            if ($run) {
                $credited[] = $run->id;
            }
        }

        // Every run this callback covered is now resolved, so whichever batch
        // they belonged to can send its next group.
        $runner->tick();

        return response()->json([
            'data' => $results,
            'sync_run_ids' => $credited,
            'orders_received' => $received,
            'orders_saved' => $saved,
            'items_created' => count($resolver->createdItems()),
        ]);
    }

    /**
     * Upsert one purchase order — header, lines, deliveries and the ERP's
     * status trail. Returns null when the order has no control_no to key on.
     *
     * $resolveItem turns a payload line into the inventory item it belongs to,
     * which is the only thing that differs between the two payload shapes.
     */
    private function saveOrder(Workspace $workspace, array $po, callable $resolveItem): ?PurchasedOrder
    {
        $controlNo = $po['control_no'] ?? null;

        if (! $controlNo) {
            return null;
        }

        $explicitStamps = $this->stageTimestamps($po);

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
                ...$explicitStamps,
            ]
        );

        $this->saveStatusLogs($order, $po, $explicitStamps);
        $this->saveLines($order, $po, $resolveItem);

        return $order;
    }

    /**
     * Write the order's lines to exactly what the payload lists.
     *
     * The ERP owns them the way it already owns deliveries and the status
     * trail: a line it has stopped reporting is gone, and its deliveries go
     * with it. Without that, resolving items by name would leave an order
     * carrying both the line it had before and the line it has now — the same
     * goods counted twice against the balance.
     *
     * Pruning only happens once at least one line resolved: a payload we
     * couldn't make sense of is no reason to throw away what we already knew.
     */
    private function saveLines(PurchasedOrder $order, array $po, callable $resolveItem): void
    {
        $lines = array_values(array_filter($po['items'] ?? [], 'is_array'));

        $kept = [];
        $first = null;

        foreach ($lines as $line) {
            $item = $resolveItem($line);

            if (! $item) {
                continue;
            }

            $orderItem = PurchasedOrderItem::updateOrCreate(
                ['inventory_purchased_order_id' => $order->id, 'inventory_item_id' => $item->id],
                [
                    'count' => (int) ($line['count'] ?? 0),
                    'amount' => $line['amount'] ?? 0,
                    'total_amount' => $line['total_amount'] ?? 0,
                ]
            );

            $kept[] = $item->id;
            $first ??= $orderItem;
        }

        if ($kept) {
            PurchasedOrderItem::where('inventory_purchased_order_id', $order->id)
                ->whereNotIn('inventory_item_id', $kept)
                ->delete();
        }

        $this->saveDeliveries($first, $po);
    }

    /**
     * Replace the line's deliveries with what the ERP sent, so delivered
     * quantities track it.
     *
     * These ERP orders carry a single line, and the payload reports deliveries
     * for the order rather than per line, so they hang off the first one. A row
     * with no qty is a settlement note rather than a receipt, and is skipped.
     */
    private function saveDeliveries(?PurchasedOrderItem $orderItem, array $po): void
    {
        if (! $orderItem || ! array_key_exists('deliveries', $po)) {
            return;
        }

        PurchasedOrderItemDelivery::where('inventory_purchased_order_item_id', $orderItem->id)->delete();

        foreach (($po['deliveries'] ?? []) as $delivery) {
            if (! is_array($delivery) || ($delivery['qty'] ?? null) === null) {
                continue;
            }

            PurchasedOrderItemDelivery::create([
                'inventory_purchased_order_item_id' => $orderItem->id,
                'delivery_date' => $this->toDate($delivery['delivery_date'] ?? $delivery['created_at'] ?? null)
                    ?? $this->toDate($po['issue_date'] ?? null)
                    ?? now()->toDateString(),
                'delivery_no' => $delivery['delivery_no'] ?? $po['delivery_no'] ?? null,
                'qty' => (int) $delivery['qty'],
            ]);
        }
    }

    /**
     * The stage timestamps the ERP sends alongside the trail, as columns to
     * write.
     *
     * Only keys actually present are returned: a field the ERP has stopped
     * sending must leave the stored value alone, while one sent as null is the
     * ERP saying the order has not reached that stage (or has been moved back),
     * and does clear it. The current payload sends none of them — see
     * saveStatusLogs(), which reads them off the trail instead.
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
     * Replace the order's status trail with what the ERP sent, and derive from
     * it every stage timestamp the ERP did not stamp itself.
     *
     * Wholesale replacement, like deliveries: the ERP owns this trail and can
     * revise it, and there is no local id to match entries on. A payload that
     * omits `statusLogs` entirely leaves the trail and the derived stamps
     * untouched — absent means "not sent", not "cleared".
     *
     * @param  array<string, mixed>  $explicitStamps
     */
    private function saveStatusLogs(PurchasedOrder $order, array $po, array $explicitStamps): void
    {
        if (! array_key_exists('statusLogs', $po)) {
            return;
        }

        // Bypasses model events on purpose: a wholesale replace would otherwise
        // re-derive the stamps once per row. The single pass below does the
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

        // A stamp the ERP sent is authoritative: re-deriving would overwrite it
        // with a reading of the same trail it came from, and disagree whenever
        // the ERP knows something the log does not. Runs even when the trail
        // came back empty, so an order whose Paid entry the ERP has withdrawn
        // stops reporting a payment date.
        $order->recalculateStageTimestamps(array_values(array_diff(
            array_keys(PurchasedOrder::STAGE_LOG_LABELS),
            array_keys($explicitStamps),
        )));
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
