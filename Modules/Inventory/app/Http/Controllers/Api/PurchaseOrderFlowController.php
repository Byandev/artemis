<?php

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Support\TeamVisibility;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Models\PurchasedOrderItem;
use Modules\Inventory\Support\InventoryStockColumns;

/**
 * The purchase-order flow panels: where ordered stock is sitting, how long it
 * has been there, and which of operations, the supplier or the warehouse is
 * holding it.
 *
 * These exist because the items list can no longer warn you on its own. Its
 * waiting-for-delivery column counts every raised purchase order — correctly,
 * since the quantity is genuinely committed — which means an item can read
 * weeks of cover while its stock sits unpaid in an approval queue. Nothing
 * there is wrong; the risk is simply invisible from a quantity column. It
 * shows up here instead, as time.
 *
 * Every endpoint is team-scoped: purchase orders through PurchasedOrder's
 * visibleTo (which reaches teams via items.inventoryItem.product.shops), and
 * item-derived figures through the same parent-aware rule the items list uses.
 */
class PurchaseOrderFlowController extends Controller
{
    use AuthorizesRequests;

    /**
     * How long an order may sit inside the business before it counts as held
     * up. Chosen against the observed internal median of ~4.7 days: a week is
     * comfortably past normal, so anything breaching it is genuinely stuck
     * rather than merely in progress.
     */
    private const INTERNAL_SLA_DAYS = 7;

    /** Age bands for the aging matrix, in days. Upper bound is inclusive. */
    private const AGE_BUCKETS = [
        ['key' => '0-7d', 'label' => '0–7d', 'max' => 7],
        ['key' => '8-14d', 'label' => '8–14d', 'max' => 14],
        ['key' => '15-30d', 'label' => '15–30d', 'max' => 30],
        ['key' => '31-60d', 'label' => '31–60d', 'max' => 60],
        ['key' => '60d+', 'label' => '60d+', 'max' => PHP_INT_MAX],
    ];

    /**
     * Stock on the shelf covering less than this many days of demand is a
     * normal picking queue, not a backlog — every warehouse has one in flight.
     */
    private const PICKING_DAYS = 3;

    /**
     * How long a supplier has, once an order is released, before it counts as
     * late.
     *
     * A fixed target rather than a figure read off `inventory_items.lead_time`:
     * that column is a flat default across almost the whole catalogue, so
     * deriving a "quote" from it implied a precision the data does not have.
     * 14 days sits between the observed P75 and P90 for issue-to-delivery, so
     * it is comfortably past normal without flagging every ordinary order.
     */
    private const SUPPLIER_TARGET_DAYS = 14;

    /**
     * Fill levels reported for the delivery curve, as a percentage of the
     * ordered quantity.
     *
     * The clock starts at the issue date — the whole door-to-door cycle, not
     * the supplier leg alone. Two reasons: it is the number that decides
     * whether stock arrives before you run out, and it needs only an issue date
     * and deliveries, so it runs over every order rather than the handful
     * carrying a release timestamp.
     *
     * Partial delivery is the norm, so a single "delivered" figure would hide
     * the shape: an order that lands 90% in a week and dribbles the last 10%
     * over a month reads very differently from one that arrives whole.
     */
    private const FILL_LEVELS = [30, 60, 90, 100];

    /**
     * The four standing figures, in the order the page reads them: what we owe,
     * what we have not ordered, what we are sitting on, and what could ship
     * today.
     *
     * One endpoint rather than four: they come from the same two scans, and
     * splitting them would run those scans four times for four numbers.
     */
    public function kpi(Request $request, Workspace $workspace)
    {
        $this->authorize('View Purchased Orders', $workspace);
        $this->authorize('View Inventory Items', $workspace);

        $lines = $this->openLines($request, $workspace);
        $split = $this->unfulfilledSplit($request, $workspace)->getData(true);
        $internal = $lines->where('kind', 'internal');

        $needed = $this->poNeededTotal($request, $workspace);

        return response()->json([
            'unfulfilled' => $split['total'],
            'not_ordered' => $needed['units'],
            'not_ordered_groups' => $needed['groups'],
            'stuck_inside' => $internal->sum('balance'),
            'stuck_overdue' => $internal->where('age', '>', self::INTERNAL_SLA_DAYS)->sum('balance'),
            'shippable_now' => $split['sitting']['units'],
            'shippable_skus' => $split['sitting']['skus'],
            'open_total' => $lines->sum('balance'),
            'sla_days' => self::INTERNAL_SLA_DAYS,
            'picking_days' => self::PICKING_DAYS,
        ]);
    }

    /**
     * Where every open unit is sitting, and how stale each pile is.
     *
     * Stages come from the data rather than a fixed list, so a workspace whose
     * ERP uses a different workflow renders without a code change.
     */
    public function pipeline(Request $request, Workspace $workspace)
    {
        $this->authorize('View Purchased Orders', $workspace);

        $lines = $this->openLines($request, $workspace);

        $stages = $lines
            ->groupBy('stage')
            ->map(fn (Collection $rows, string $stage) => [
                'name' => $stage,
                'kind' => $rows->first()->kind,
                'units' => $rows->sum('balance'),
                'orders' => $rows->pluck('purchased_order_id')->unique()->count(),
                'oldest' => (int) $rows->max('age'),
                'overdue_units' => $rows->where('kind', 'internal')
                    ->where('age', '>', self::INTERNAL_SLA_DAYS)
                    ->sum('balance'),
                'buckets' => collect(self::AGE_BUCKETS)
                    ->mapWithKeys(fn (array $b) => [
                        $b['key'] => $rows->filter(fn ($r) => $this->bucketFor($r->age) === $b['key'])->sum('balance'),
                    ]),
            ])
            // Workflow order, not alphabetical: the bar reads left to right.
            ->sortBy(fn (array $stage) => array_search($stage['name'], PurchasedOrder::STATUSES, true) ?: 99)
            ->values();

        $internal = $lines->where('kind', 'internal');
        $supplier = $lines->where('kind', 'supplier');

        return response()->json([
            'stages' => $stages,
            'buckets' => collect(self::AGE_BUCKETS)->map(fn ($b) => ['key' => $b['key'], 'label' => $b['label']]),
            'internal_units' => $internal->sum('balance'),
            'supplier_units' => $supplier->sum('balance'),
            'total_units' => $lines->sum('balance'),
            'sla_days' => self::INTERNAL_SLA_DAYS,
            // What the reorder maths still wants bought once every open order
            // has been credited against it.
            'po_needed' => $this->poNeededTotal($request, $workspace)['units'],
        ]);
    }

    /**
     * Orders held inside the business, longest wait first — the list someone
     * clears rather than a chart someone admires.
     */
    public function worklist(Request $request, Workspace $workspace)
    {
        $this->authorize('View Purchased Orders', $workspace);

        $rows = $this->openLines($request, $workspace)
            ->where('kind', 'internal')
            ->sortByDesc('age')
            ->values()
            ->map(fn ($r) => [
                'id' => $r->id,
                'purchased_order_id' => $r->purchased_order_id,
                'po' => $r->control_no ?? $r->cust_po_no ?? "#{$r->purchased_order_id}",
                'stage' => $r->stage,
                'status' => $r->status,
                'age' => $r->age,
                'overdue' => $r->age > self::INTERNAL_SLA_DAYS,
                'item' => $r->group_sku,
                'product_name' => $r->product_name,
                'units' => $r->balance,
                'covers_days' => $r->covers,
            ]);

        return response()->json([
            'lines' => $rows,
            'total_units' => $rows->sum('units'),
            'overdue_units' => $rows->where('overdue', true)->sum('units'),
            'sla_days' => self::INTERNAL_SLA_DAYS,
        ]);
    }

    /**
     * Orders a supplier already has and has not finished delivering.
     *
     * Split three ways because they need different conversations: nothing
     * arrived at all, started then stalled, and past the quoted lead time.
     */
    public function supplierDeliveries(Request $request, Workspace $workspace)
    {
        $this->authorize('View Purchased Orders', $workspace);

        $rows = $this->openLines($request, $workspace)
            ->where('kind', 'supplier')
            ->sortByDesc('age')
            ->values()
            ->map(fn ($r) => [
                'id' => $r->id,
                'purchased_order_id' => $r->purchased_order_id,
                'po' => $r->control_no ?? $r->cust_po_no ?? "#{$r->purchased_order_id}",
                'supplier' => $r->supplier,
                'stage' => $r->stage,
                'age' => $r->age,
                'item' => $r->group_sku,
                'product_name' => $r->product_name,
                'ordered' => $r->ordered,
                'delivered' => $r->delivered,
                'balance' => $r->balance,
                'fill_pct' => $r->ordered > 0 ? (int) round(100 * $r->delivered / $r->ordered) : 0,
                'last_delivery' => $r->last_delivery,
                'covers_days' => $r->covers,
            ]);

        $quoted = self::SUPPLIER_TARGET_DAYS;

        // Counted per order, not per line: one purchase order carrying three
        // late lines is one phone call, and "28 orders overdue" would overstate
        // the work if it were really 28 lines across 12 orders.
        $summarise = fn (Collection $rows) => [
            'orders' => $rows->pluck('purchased_order_id')->unique()->count(),
            'lines' => $rows->count(),
            'units' => $rows->sum('balance'),
        ];

        return response()->json([
            'lines' => $rows,
            'quoted_days' => $quoted,
            'nothing_arrived' => $summarise($rows->where('delivered', 0)),
            'part_delivered' => $summarise($rows->where('delivered', '>', 0)),
            'past_quote' => $summarise($rows->where('age', '>', $quoted)),
            'total_units' => $rows->sum('balance'),
        ]);
    }

    /**
     * How long each workflow step takes, from the ERP's own status trail.
     *
     * Also reports how tightly transitions cluster: a stage where half the
     * moves land on three days is a queue waiting for someone to run a batch,
     * not a stage where the work itself is slow.
     */
    public function stageTimings(Request $request, Workspace $workspace)
    {
        $this->authorize('View Purchased Orders', $workspace);

        $orders = PurchasedOrder::query()
            ->where('workspace_id', $workspace->id)
            ->whereHas('statusLogs')
            ->visibleTo($request->user(), $workspace)
            ->with(['statusLogs', 'items.deliveries'])
            ->get();

        $steps = [
            ['label' => 'Raised → Approve', 'from' => null, 'to' => 'Approve', 'kind' => 'internal'],
            ['label' => 'Approve → To Pay', 'from' => 'Approve', 'to' => 'To Pay', 'kind' => 'internal'],
            ['label' => 'To Pay → Paid', 'from' => 'To Pay', 'to' => 'Paid', 'kind' => 'internal'],
            ['label' => 'Paid → For Purchase', 'from' => 'Paid', 'to' => 'For Purchase', 'kind' => 'internal'],
            ['label' => 'For Purchase → Purchased', 'from' => 'For Purchase', 'to' => 'Purchased', 'kind' => 'internal'],
        ];

        $samples = array_fill_keys(array_column($steps, 'label'), []);
        $internalTotal = [];
        $toRelease = [];
        $toFirst = [];
        $toFill = array_fill_keys(self::FILL_LEVELS, []);

        foreach ($orders as $order) {
            $at = fn (string $label) => $order->statusLogs
                ->first(fn ($log) => strcasecmp(trim($log->status), $label) === 0)?->logged_at;

            foreach ($steps as $step) {
                $from = $step['from'] === null ? $order->issue_date : $at($step['from']);
                $to = $at($step['to']);

                if ($from && $to) {
                    $days = $from->diffInDays($to, absolute: false);
                    if ($days >= 0) {
                        $samples[$step['label']][] = round($days, 2);
                    }
                }
            }

            $released = $at('Purchased') ?? $at('For Purchase');

            if ($order->issue_date && $released) {
                $internalTotal[] = round(max(0, $order->issue_date->diffInDays($released, absolute: false)), 2);
            }

            if (! $released) {
                continue;
            }

            $first = $order->items->flatMap->deliveries
                ->filter(fn ($d) => $d->delivery_date)
                ->min('delivery_date');

            if ($first) {
                $toRelease[] = round(max(0, $released->copy()->startOfDay()->diffInDays($first, absolute: false)), 2);
            }
        }

        // The delivery curve runs over every visible order, not just the ones
        // carrying a status trail: it needs an issue date and deliveries, both
        // of which every order has.
        $withDeliveries = PurchasedOrder::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', '!=', PurchasedOrder::CANCELLED)
            ->whereNotNull('issue_date')
            ->visibleTo($request->user(), $workspace)
            ->with('items.deliveries')
            ->get();

        foreach ($withDeliveries as $order) {
            $ordered = (int) $order->items->sum('count');

            if ($ordered <= 0) {
                continue;
            }

            $deliveries = $order->items->flatMap->deliveries
                ->filter(fn ($d) => $d->delivery_date)
                ->sortBy(fn ($d) => $d->delivery_date->getTimestamp());

            if ($deliveries->isEmpty()) {
                continue;
            }

            $issuedOn = $order->issue_date;
            $toFirst[] = round(max(0, $issuedOn->diffInDays($deliveries->first()->delivery_date, absolute: false)), 2);

            // Walk the deliveries once, stamping each level with the date that
            // carried cumulative receipts past it. Several levels land on the
            // same date when one delivery jumps past them all.
            $cumulative = 0;
            $stamped = [];

            foreach ($deliveries as $delivery) {
                $cumulative += max(0, (int) $delivery->qty);
                $elapsed = round(max(0, $issuedOn->diffInDays($delivery->delivery_date, absolute: false)), 2);

                foreach (self::FILL_LEVELS as $level) {
                    if (! isset($stamped[$level]) && $cumulative * 100 >= $ordered * $level) {
                        $stamped[$level] = $elapsed;
                        $toFill[$level][] = $elapsed;
                    }
                }
            }
        }

        $describe = fn (string $label, string $kind, array $v) => [
            'label' => $label,
            'kind' => $kind,
            'samples' => count($v),
            'p50' => $this->percentile($v, 50),
            'p90' => $this->percentile($v, 90),
        ];

        $rows = collect($steps)
            ->map(fn (array $s) => $describe($s['label'], $s['kind'], $samples[$s['label']]))
            // Steps that are always instantaneous are one click, not a queue —
            // listing them as zero-length bars just adds noise.
            ->filter(fn (array $r) => $r['samples'] > 0 && $r['p90'] > 0)
            ->values();

        // The delivery curve, measured door to door. Levels nothing has reached
        // are dropped rather than shown empty — an unreached level is unknown,
        // not instant.
        $deliverySteps = collect([$describe('Raised → first delivery', 'total', $toFirst)])
            ->concat(collect(self::FILL_LEVELS)
                ->map(fn (int $level) => $describe("Raised → {$level}% delivered", 'total', $toFill[$level])))
            ->filter(fn (array $r) => $r['samples'] > 0)
            ->values();

        return response()->json([
            'steps' => $rows,
            'internal_total' => $describe('Total inside', 'internal', $internalTotal),
            'released_to_delivery' => $describe('Released → first delivery', 'supplier', $toRelease),
            'delivery_steps' => $deliverySteps,
            'fill_levels' => self::FILL_LEVELS,
            'clustering' => $this->clustering($orders),
            'orders_sampled' => $orders->count(),
        ]);
    }

    /**
     * Unmet demand split by whether the stock is physically on the shelf.
     *
     * Computed per SKU then rolled to the group: a customer ordered a specific
     * variant, so stock on one child cannot ship an order placed against
     * another. Anything shippable is the warehouse's to clear; the rest is
     * waiting on supply.
     */
    public function unfulfilledSplit(Request $request, Workspace $workspace)
    {
        $this->authorize('View Inventory Items', $workspace);

        $items = $this->visibleItems($request, $workspace)
            // When stock last arrived and last left. On a row that says stock is
            // sitting here, those two dates are the difference between "nothing
            // is coming in" and "nothing is going out".
            ->leftJoinSub($this->lastMovementQuery(), 'mv', 'mv.inventory_item_id', '=', 'inventory_items.id')
            ->selectRaw('inventory_items.id, inventory_items.parent_id, inventory_items.is_parent, inventory_items.sku, inventory_items.unfulfilled_count, inventory_items.three_days_average')
            ->selectRaw('mv.last_in, mv.last_out')
            ->selectRaw(InventoryStockColumns::currentStocks().' as current_stocks')
            ->with('parent:id,sku')
            ->get();

        $groups = [];

        foreach ($items as $item) {
            $unfulfilled = max(0, (int) round((float) $item->unfulfilled_count));
            $stock = max(0, (int) round((float) $item->current_stocks));
            $key = (int) ($item->parent_id ?? $item->id);

            $groups[$key] ??= [
                'id' => $key,
                'sku' => (string) ($item->parent?->sku ?? $item->sku),
                'unfulfilled' => 0,
                'here' => 0,
                'gone' => 0,
                'avg' => 0.0,
                'last_in' => null,
                'last_out' => null,
            ];

            $groups[$key]['unfulfilled'] += $unfulfilled;
            $groups[$key]['here'] += min($stock, $unfulfilled);
            $groups[$key]['gone'] += max(0, $unfulfilled - $stock);
            $groups[$key]['avg'] += (float) $item->three_days_average;
            // The group's latest movement is the latest across its children:
            // any sibling receiving or shipping means the group moved.
            $groups[$key]['last_in'] = max($groups[$key]['last_in'], $item->last_in);
            $groups[$key]['last_out'] = max($groups[$key]['last_out'], $item->last_out);
            // A parent placeholder carries the group's name once it appears.
            if ($item->is_parent) {
                $groups[$key]['sku'] = (string) $item->sku;
            }
        }

        $rows = collect($groups)
            ->filter(fn (array $g) => $g['unfulfilled'] > 0)
            ->sortByDesc('unfulfilled')
            ->values()
            ->map(fn (array $g) => [
                'id' => $g['id'],
                'item' => $g['sku'],
                'unfulfilled' => $g['unfulfilled'],
                'here' => $g['here'],
                'gone' => $g['gone'],
                // Days of demand the shippable part represents — what separates
                // a normal picking queue from stock nobody is moving.
                'here_days' => $g['avg'] > 0 ? round($g['here'] / $g['avg'], 1) : null,
                // Date-only; the client formats them and works out how long ago.
                'last_in' => $g['last_in'] ? CarbonImmutable::parse($g['last_in'])->toDateString() : null,
                'last_out' => $g['last_out'] ? CarbonImmutable::parse($g['last_out'])->toDateString() : null,
            ]);

        $sitting = $rows->filter(fn ($r) => $r['here_days'] !== null && $r['here_days'] > self::PICKING_DAYS);

        return response()->json([
            'items' => $rows,
            'here' => $rows->sum('here'),
            'gone' => $rows->sum('gone'),
            'total' => $rows->sum('unfulfilled'),
            'picking_days' => self::PICKING_DAYS,
            'sitting' => ['skus' => $sitting->count(), 'units' => $sitting->sum('here')],
            'worst' => $sitting->sortByDesc('here_days')->first(),
        ]);
    }

    /**
     * Which of the three owns the hold-up, scored independently so the verdict
     * follows the data rather than the story anyone expects.
     */
    public function bottleneck(Request $request, Workspace $workspace)
    {
        $this->authorize('View Purchased Orders', $workspace);
        $this->authorize('View Inventory Items', $workspace);

        $lines = $this->openLines($request, $workspace);
        $internal = $lines->where('kind', 'internal');
        $supplier = $lines->where('kind', 'supplier');
        $quoted = self::SUPPLIER_TARGET_DAYS;

        $internalUnits = $internal->sum('balance');
        $supplierUnits = $supplier->sum('balance');
        $internalOverdue = $internal->where('age', '>', self::INTERNAL_SLA_DAYS)->sum('balance');
        $supplierOverdue = $supplier->where('age', '>', $quoted)->sum('balance');

        $split = $this->unfulfilledSplit($request, $workspace)->getData(true);
        $unfulfilledTotal = max(1, (int) $split['total']);
        $sittingShare = $split['sitting']['units'] / $unfulfilledTotal;

        $owners = [
            [
                'key' => 'operations',
                'name' => 'Operations',
                'question' => 'Are we processing purchase orders on time?',
                'state' => $internalOverdue > 0 ? 'blocked' : ($internalUnits > $supplierUnits ? 'watch' : 'ok'),
                'value' => $internalUnits,
                'unit' => 'units pending for approval or payment',
                'facts' => [
                    ['Past the target', $internalOverdue ?: null, 'units'],
                    ['Longest wait', $internal->max('age'), 'days'],
                    ['Orders held', $internal->pluck('purchased_order_id')->unique()->count(), 'orders'],
                ],
            ],
            [
                'key' => 'supplier',
                'name' => 'Supplier',
                'question' => 'Are suppliers delivering what we have released to them?',
                'state' => $supplierUnits > 0 && $supplierOverdue > $supplierUnits * 0.4
                    ? 'blocked'
                    : ($supplierOverdue > 0 ? 'watch' : 'ok'),
                'value' => $supplierUnits,
                'unit' => 'paid and waiting for delivery',
                'facts' => [
                    ['Past the target', $supplierOverdue ?: null, 'units'],
                    ['Delivery target', $quoted, 'days'],
                    ['Longest wait', $supplier->max('age'), 'days'],
                ],
            ],
            [
                'key' => 'warehouse',
                'name' => 'Warehouse',
                'question' => 'Is stock sitting here that an unfulfilled order could already take?',
                'state' => $sittingShare > 0.15 ? 'blocked' : ($sittingShare > 0.05 ? 'watch' : 'ok'),
                'value' => $split['sitting']['units'],
                'unit' => 'orders with available stocks in warehouse',
                'facts' => [
                    ['Could ship today', $split['here'] ?: null, 'units'],
                    ['SKUs affected', $split['sitting']['skus'] ?: null, 'SKUs'],
                    ['No stock to give', $split['gone'] ?: null, 'units'],
                ],
            ],
        ];

        return response()->json([
            'owners' => $owners,
            'blocked' => collect($owners)->where('state', 'blocked')->pluck('key')->values(),
            'internal_units' => $internalUnits,
            'supplier_units' => $supplierUnits,
            'sla_days' => self::INTERNAL_SLA_DAYS,
        ]);
    }

    // ── shared query layer ───────────────────────────────────────────────────

    /**
     * Every open purchase-order line still owing stock, team-scoped, decorated
     * with the few derived values every panel needs.
     *
     * One query feeds the pipeline, worklist, supplier and bottleneck panels
     * rather than four near-identical ones. Open orders number in the tens, so
     * the per-line work happens in PHP where it reads plainly.
     *
     * @return Collection<int, object>
     */
    private function openLines(Request $request, Workspace $workspace): Collection
    {
        $demand = $this->groupDemand($request, $workspace);
        $items = InventoryItem::where('workspace_id', $workspace->id)->get(['id', 'parent_id', 'sku'])->keyBy('id');

        return PurchasedOrderItem::query()
            ->whereHas('purchasedOrder', fn (Builder $q) => $q
                ->where('workspace_id', $workspace->id)
                ->whereIn('status', PurchasedOrder::AWAITING_DELIVERY_STATUSES)
                ->whereNotNull('issue_date')
                ->visibleTo($request->user(), $workspace))
            ->with([
                'purchasedOrder:id,control_no,cust_po_no,supplier,status,issue_date,paid_at',
                'deliveries:id,inventory_purchased_order_item_id,delivery_date,qty',
                'inventoryItem:id,sku,parent_id,product_id',
                'inventoryItem.product:id,name',
            ])
            ->get()
            ->map(function (PurchasedOrderItem $line) use ($items, $demand) {
                $order = $line->purchasedOrder;
                $delivered = (int) $line->deliveries->sum('qty');
                $balance = max(0, (int) $line->count - $delivered);
                $groupId = (int) ($items->get($line->inventory_item_id)?->parent_id ?? $line->inventory_item_id);
                $avg = $demand[$groupId] ?? 0.0;

                return (object) [
                    'id' => $line->id,
                    'purchased_order_id' => $order->id,
                    'control_no' => $order->control_no,
                    'cust_po_no' => $order->cust_po_no,
                    'supplier' => $order->supplier,
                    'status' => (int) $order->status,
                    'stage' => $order->status_label,
                    'kind' => in_array($order->status, PurchasedOrder::RELEASED_STATUSES, true) ? 'supplier' : 'internal',
                    'age' => (int) $order->issue_date->diffInDays(now(), absolute: false),
                    'ordered' => (int) $line->count,
                    'delivered' => $delivered,
                    'balance' => $balance,
                    'last_delivery' => $line->deliveries
                        ->filter(fn ($d) => $d->delivery_date)
                        ->max('delivery_date')?->toDateString(),
                    'group_sku' => (string) ($items->get($groupId)?->sku ?? $line->inventoryItem?->sku ?? '—'),
                    'product_name' => $line->inventoryItem?->product?->name,
                    // How many days of demand this line is holding up. Units
                    // alone rank wrongly: 3,400 of a slow mover can be more
                    // urgent than 13,000 of a fast one.
                    'covers' => $avg > 0 ? round($balance / $avg, 1) : null,
                ];
            })
            // Fully delivered lines owe nothing, so they are not "open" to anyone.
            ->filter(fn ($line) => $line->balance > 0)
            ->values();
    }

    /**
     * The last date each item took stock in and the last it sent stock out,
     * from the transaction ledger.
     *
     * "In" and "out" are the purchase-order movements specifically — receipts
     * and despatches — not RTS returns or write-offs, which say nothing about
     * whether the warehouse is working.
     */
    private function lastMovementQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('inventory_transactions')
            ->groupBy('inventory_item_id')
            ->select('inventory_item_id')
            ->selectRaw('MAX(CASE WHEN po_qty_in > 0 THEN date END) as last_in')
            ->selectRaw('MAX(CASE WHEN po_qty_out > 0 THEN date END) as last_out');
    }

    /**
     * Daily demand per item group, summed across the group's visible children.
     *
     * @return array<int, float>
     */
    private function groupDemand(Request $request, Workspace $workspace): array
    {
        $demand = [];

        foreach ($this->visibleItems($request, $workspace)->get(['inventory_items.id', 'inventory_items.parent_id', 'inventory_items.three_days_average']) as $item) {
            $key = (int) ($item->parent_id ?? $item->id);
            $demand[$key] = ($demand[$key] ?? 0.0) + (float) $item->three_days_average;
        }

        return $demand;
    }

    /**
     * What the reorder maths still wants bought, after every open purchase
     * order is credited against it.
     *
     * Recomputed per group, never summed per SKU. po_needed is not additive:
     * each sibling in a group carries the whole group's lead-time and buffer
     * demand, so adding their figures counts that demand once per sibling —
     * 69,398 units instead of 17,748 on this workspace. Mirrors
     * InventoryItemController::buildSummaryQuery(), which is what the items
     * list shows in its default summarize view.
     *
     * @return array{units: int, groups: int}
     */
    private function poNeededTotal(Request $request, Workspace $workspace): array
    {
        $inner = $this->visibleItems($request, $workspace)
            ->selectRaw('inventory_items.id, inventory_items.parent_id, inventory_items.is_parent,
                inventory_items.lead_time, inventory_items.days_of_coverage, inventory_items.three_days_average')
            ->selectRaw(InventoryStockColumns::remainingAfterFulfillment().' as remaining_after_fulfillment');

        // The group's lead time and buffer are the parent's when it has one,
        // else the max across the group — same rule the items list applies.
        $lead = 'COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.lead_time END), MAX(sub.lead_time))';
        $cover = 'COALESCE(MAX(CASE WHEN sub.is_parent = 1 THEN sub.days_of_coverage END), MAX(sub.days_of_coverage))';
        $avg = 'SUM(sub.three_days_average)';
        $remaining = 'COALESCE(SUM(sub.remaining_after_fulfillment), 0)';
        $needed = "GREATEST(0, ($cover * $avg) + ($lead * $avg) - $remaining)";

        $groups = DB::query()
            ->fromSub($inner, 'sub')
            ->selectRaw("$needed as needed")
            ->groupByRaw('COALESCE(sub.parent_id, sub.id)')
            ->havingRaw("$needed > 0")
            ->get();

        return [
            'units' => (int) round((float) $groups->sum('needed')),
            'groups' => $groups->count(),
        ];
    }

    /**
     * How tightly a stage's transitions cluster onto a few days. Work spreads
     * out in proportion to volume; bursts mean queueing.
     *
     * @param  Collection<int, PurchasedOrder>  $orders
     * @return array<int, array<string, mixed>>
     */
    private function clustering(Collection $orders): array
    {
        $out = [];

        foreach (['Approve' => 'approvals', 'Paid' => 'payments', 'Purchased' => 'releases'] as $label => $name) {
            $byDay = [];

            foreach ($orders as $order) {
                $stamp = $order->statusLogs
                    ->first(fn ($log) => strcasecmp(trim($log->status), $label) === 0)?->logged_at;

                if ($stamp) {
                    $day = $stamp->toDateString();
                    $byDay[$day] = ($byDay[$day] ?? 0) + 1;
                }
            }

            $events = array_sum($byDay);

            if ($events === 0) {
                continue;
            }

            arsort($byDay);
            $busiest = array_sum(array_slice($byDay, 0, 3));

            $out[] = [
                'label' => $name,
                'events' => $events,
                'days' => count($byDay),
                'busiest_three_pct' => (int) round(100 * $busiest / $events),
            ];
        }

        return $out;
    }

    /**
     * Active items the user may see. Deliberately not the model's visibleTo
     * scope: a parent placeholder has no product of its own, so scoping it
     * directly would drop every group. Mirrors the items list.
     *
     * @return Builder<InventoryItem>
     */
    private function visibleItems(Request $request, Workspace $workspace): Builder
    {
        $query = InventoryItem::where('inventory_items.workspace_id', $workspace->id)
            ->where('inventory_items.is_active', true);

        InventoryStockColumns::applyJoins($query);

        $teamIds = TeamVisibility::scopeTeamIds($request->user(), $workspace);

        if ($teamIds === null) {
            return $query;
        }

        if (empty($teamIds)) {
            return $query->whereRaw('1 = 0');
        }

        $inTeams = fn ($q) => $q->whereHas('product.shops.teams', fn ($t) => $t->whereIn('teams.id', $teamIds));

        return $query->where(fn ($q) => $inTeams($q)->orWhereHas('children', $inTeams));
    }

    /** Which age band a number of days falls in. */
    private function bucketFor(int $days): string
    {
        foreach (self::AGE_BUCKETS as $bucket) {
            if ($days <= $bucket['max']) {
                return $bucket['key'];
            }
        }

        return self::AGE_BUCKETS[array_key_last(self::AGE_BUCKETS)]['key'];
    }

    /**
     * Percentile of a sample, linearly interpolated. Null for an empty sample —
     * "no data" and "zero days" are different answers.
     *
     * @param  array<int, float>  $values
     */
    private function percentile(array $values, float $p): ?float
    {
        if (! $values) {
            return null;
        }

        sort($values);
        $index = ($p / 100) * (count($values) - 1);
        $low = (int) floor($index);
        $high = (int) ceil($index);

        return round($values[$low] + ($values[$high] - $values[$low]) * ($index - $low), 1);
    }
}
