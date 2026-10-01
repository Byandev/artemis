<?php

namespace Modules\Inventory\Support;

use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\PurchasedOrder;

/**
 * The per-group figures the item report adds on top of the items list: demand
 * over several windows, when stock last moved, and what each group's purchase
 * orders are doing.
 *
 * Computed here rather than as more selectRaw columns on the list query for two
 * reasons. They are wanted only by the export, so the list should not pay for
 * them; and each is a grouped scan over a different table, which as correlated
 * subqueries would re-run once per row. Three scans, held in memory, keyed by
 * group — the same shape buildSummaryQuery() rolls up to.
 *
 * "Group" throughout means COALESCE(parent_id, id): a parent and its children
 * are one line of the report, because a customer ordering a variant is demand
 * against the group's supply.
 */
class ItemReportFacts
{
    /**
     * Demand windows reported, in days. Three so the report can show whether
     * demand is accelerating; the 3-day window matches the stored
     * three_days_average the rest of the module is built on.
     */
    public const WINDOWS = [3, 7, 14];

    /**
     * Columns recorded per ITEM rather than per group, and summed by the roll-up
     * instead of MAX'd.
     *
     * units_3d is the workspace's demand rate, and every reorder figure divides
     * by it or multiplies through it — so it has to answer for exactly the rows
     * on screen. Held per group it could not: on the flat list one SKU would
     * report the whole group's demand, and a filter hiding half a group would
     * narrow the stock while leaving the demand whole.
     *
     * The wider windows stay per group deliberately. Nothing computes from them
     * — they are read side by side in the roll-up to show whether demand is
     * accelerating — and the distinct-order counts they sit beside genuinely
     * cannot be split between siblings, so keeping the pair at one grain is
     * what makes them comparable.
     *
     * @var list<string>
     */
    public const ITEM_COLUMNS = ['units_3d'];

    /** The window ITEM_COLUMNS covers. The one the reorder maths divides by. */
    public const ITEM_WINDOW = 3;

    /**
     * The fact keys frozen into inventory_item_snapshots, which are also the
     * column names there — the two are deliberately the same word, so a snapshot
     * row can be handed to the export as-is with no translation layer to drift.
     *
     * demand_as_of is stored alongside them but is provenance rather than a
     * fact, so it is not listed here.
     *
     * @var list<string>
     */
    public const SNAPSHOT_COLUMNS = [
        'orders_3d', 'orders_7d', 'units_7d', 'orders_14d', 'units_14d',
        'unfulfilled_orders_count',
        'last_in_date', 'last_in_count', 'last_out_date', 'last_out_count',
        'last_po_date', 'last_po_count', 'raised_not_created_days', 'raised_not_created_units',
        'earliest_expected_date', 'earliest_expected_count', 'longest_waiting_date',
        'longest_waiting_count', 'delayed_po', 'bottleneck_stage',
    ];

    /**
     * Thresholds behind the bottleneck classification.
     *
     * Stock that could ship and has not moved for longer than this is the
     * warehouse's to clear; it matches the Supplier card's own ship target on
     * the flow dashboard.
     */
    private const SHIP_TARGET_DAYS = 2;

    /** Demand running this far above its two-week baseline is scaling, not noise. */
    private const SCALING_TREND_PCT = 120;

    /** Cover shorter than this is close enough to a stockout to be the story. */
    private const LATE_PO_COVER_DAYS = 15;

    /** The four owners a hold-up can belong to, in the order they are tested. */
    private const BOTTLENECK_WAREHOUSE = 'Warehouse';

    private const BOTTLENECK_STOCKS = 'Delayed Stocks';

    private const BOTTLENECK_SCALING = 'Scaling Item';

    private const BOTTLENECK_LATE_PO = 'Late PO';

    /**
     * The stages a group can be labelled with, in the order they are tested.
     *
     * Public because the items list offers them as a filter: the page must not
     * carry its own copy of these strings, or a rename here would quietly leave
     * a filter matching nothing.
     *
     * @var list<string>
     */
    public const BOTTLENECK_STAGES = [
        self::BOTTLENECK_WAREHOUSE,
        self::BOTTLENECK_STOCKS,
        self::BOTTLENECK_SCALING,
        self::BOTTLENECK_LATE_PO,
    ];

    /** @var array<int, array<string, mixed>> group id => facts */
    private array $facts = [];

    /**
     * The latest order date the Gencys feed reaches. Every demand window is
     * measured back from here rather than from today, because the feed lands in
     * batches and routinely runs days behind: anchored to today, a feed that
     * paused last week reports every 3-day average as zero.
     */
    private ?CarbonImmutable $demandAsOf = null;

    /** Per-item demand, keyed by inventory item id. @var array<int, array<string, int>> */
    private array $itemFacts = [];

    /**
     * @param  list<int>|null  $groupIds  restrict to these groups, or null for all.
     *                                    The list view passes the page it is about
     *                                    to render, which keeps the scans
     *                                    proportional to what is on screen rather
     *                                    than to the catalogue.
     */
    /** Memoised unit-code expansion; two scans read it. @var array{0: array, 1: array}|null */
    private ?array $unitCodeMaps = null;

    /** Memoised last day the transaction ledger covers. */
    private ?CarbonImmutable $ledgerAsOf = null;

    public function __construct(private Workspace $workspace, private ?array $groupIds = null)
    {
        $this->demandWindows();
        $this->unfulfilledOrders();
        $this->movements();
        $this->purchaseOrders();
    }

    /** Facts for one group, with every key present so the export can be dumb. */
    public function for(int $groupId): array
    {
        return ($this->facts[$groupId] ?? []) + $this->blank();
    }

    /**
     * The per-item columns for one item, defaulted.
     *
     * Zero rather than null for an item the feed never named: the scan covered
     * the window and found nothing for it, which is a measured zero. Null is
     * reserved for a workspace whose feed has not arrived at all, and that case
     * never reaches here — demandWindows() returns before recording anything.
     *
     * @return array<string, int|null>
     */
    public function itemFacts(int $itemId): array
    {
        $blank = array_fill_keys(self::ITEM_COLUMNS, $this->demandAsOf === null ? null : 0);

        return ($this->itemFacts[$itemId] ?? []) + $blank;
    }

    /** The Gencys feed's own latest day, which the demand windows are measured from. */
    public function demandAsOf(): ?CarbonImmutable
    {
        return $this->demandAsOf;
    }

    /**
     * Every key the export can read, defaulted. A group with no orders, no
     * movements and no purchase orders still renders a full row.
     *
     * @return array<string, mixed>
     */
    private function blank(): array
    {
        $blank = [
            'last_in_date' => null,
            'last_in_count' => null,
            'last_out_date' => null,
            'last_out_count' => null,
            'last_po_date' => null,
            'last_po_count' => null,
            'raised_not_created_days' => null,
            'raised_not_created_units' => null,
            'earliest_expected_date' => null,
            'earliest_expected_count' => null,
            'longest_waiting_date' => null,
            'longest_waiting_count' => null,
            'delayed_po' => 0,
            'bottleneck_stage' => null,
            // A measured zero: the scan covered every open order and found none
            // touching this group.
            'unfulfilled_orders_count' => 0,
        ];

        foreach (self::WINDOWS as $days) {
            $blank["orders_{$days}d"] = 0;
            $blank["units_{$days}d"] = 0;
        }

        return $blank;
    }

    /**
     * Orders and units per group over each window, from the Gencys order feed.
     *
     * An order line names a unit code, not an inventory item, so it is expanded
     * through inventory_unit_code_items into its components — one line for a
     * three-item bundle is three units of demand, one per component, times that
     * component's quantity per bundle. This is the same expansion the demand
     * sync performs; counting the line's own quantity instead would count
     * bundles rather than stock.
     *
     * The expansion is resolved in PHP, and deliberately so. Unit codes match
     * order lines only after trimming and upper-casing, and expressing that as a
     * SQL join makes every index unusable: joined that way over a few months of
     * orders, the three windows took 13s, 25s and 39s — enough to time the
     * request out. The lookup tables are a few hundred rows, so building the map
     * in memory costs nothing and leaves one indexed scan against
     * (workspace_id, order_date).
     *
     * All three windows come from that single scan of the widest one, and orders
     * are counted distinct because one order carrying two children of the same
     * group is still one order to pick. Rows arrive ordered by order id, so
     * "same order as the last row" is all the state that needs keeping.
     */
    private function demandWindows(): void
    {
        $latest = DB::table('gencys_orders')
            ->where('workspace_id', $this->workspace->id)
            ->max('order_date');

        if (! $latest) {
            return;
        }

        $this->demandAsOf = CarbonImmutable::parse($latest)->endOfDay();

        [$itemsByCode, $groupByItem] = $this->unitCodeMaps();

        if (! $itemsByCode) {
            return;
        }

        $starts = [];

        foreach (self::WINDOWS as $days) {
            // days - 1, because demandAsOf is the end of the latest day and that
            // day is one of them: subDays(3) from the end of the 11th reaches
            // the start of the 8th, which is four days of orders divided by
            // three. Every rate on the page would read a third high.
            $starts[$days] = $this->demandAsOf->subDays($days - 1)->startOfDay();
        }

        $widest = min($starts);
        // Units land on the item that ships them; orders land on the group,
        // because one order carrying two siblings is still one order to pick.
        $itemUnits = [];
        $groupUnits = [];
        $orders = [];
        $lastOrder = [];

        $rows = DB::table('gencys_orders as go')
            ->join('gencys_order_items as goi', 'goi.order_id', '=', 'go.id')
            ->where('go.workspace_id', $this->workspace->id)
            ->whereNotNull('goi.sku')
            ->whereBetween('go.order_date', [$widest, $this->demandAsOf])
            ->orderBy('go.id')
            ->select('go.id as order_id', 'go.order_date', 'goi.sku')
            ->cursor();

        foreach ($rows as $row) {
            $components = $itemsByCode[mb_strtoupper(trim((string) $row->sku))] ?? null;

            if ($components === null) {
                continue;
            }

            $orderedAt = CarbonImmutable::parse($row->order_date);

            foreach (self::WINDOWS as $days) {
                if ($orderedAt < $starts[$days]) {
                    continue;
                }

                foreach ($components as $item => $perBundle) {
                    $group = $groupByItem[$item];

                    $itemUnits[$days][$item] = ($itemUnits[$days][$item] ?? 0) + $perBundle;
                    $groupUnits[$days][$group] = ($groupUnits[$days][$group] ?? 0) + $perBundle;

                    // Counted once per order per group: a bundle holding two
                    // siblings must not read as two orders against the group.
                    if (($lastOrder[$days][$group] ?? null) !== $row->order_id) {
                        $lastOrder[$days][$group] = $row->order_id;
                        $orders[$days][$group] = ($orders[$days][$group] ?? 0) + 1;
                    }
                }
            }
        }

        foreach (self::WINDOWS as $days) {
            foreach ($groupUnits[$days] ?? [] as $group => $total) {
                $this->facts[$group]["units_{$days}d"] = $total;
                $this->facts[$group]["orders_{$days}d"] = $orders[$days][$group] ?? 0;
            }
        }

        // The 3-day window again, kept per item — see ITEM_COLUMNS.
        foreach ($itemUnits[self::ITEM_WINDOW] ?? [] as $item => $total) {
            $this->itemFacts[$item]['units_'.self::ITEM_WINDOW.'d'] = $total;
        }
    }

    /**
     * Normalised order-line sku => [item id => units per bundle], plus the
     * item => group map the order counts are tallied on.
     *
     * Resolved to the ITEM rather than collapsed straight to the group, because
     * units are recorded per item now (see ITEM_COLUMNS) while distinct orders
     * are still counted per group. Both need the same expansion, so it is done
     * once and the caller folds items up where it needs the group.
     *
     * Keyed by both the unit code's label and its own sku, because an order line
     * names it by either — the same pair GencysDemandSync accepts. Components
     * landing on the same item are summed: a bundle holding it twice is two
     * units of demand.
     *
     * @return array{0: array<string, array<int, int>>, 1: array<int, int>}
     */
    private function unitCodeMaps(): array
    {
        return $this->unitCodeMaps ??= $this->itemsByUnitCode();
    }

    /**
     * How many distinct orders are committed but not yet shipped, per group.
     *
     * The sibling of unfulfilled_count, which counts the units those orders
     * owe. Both answer "what do we already owe", one in stock and one in
     * pickable orders, and the items list shows whichever the Unit/Order toggle
     * is set to.
     *
     * Counted rather than converted. The obvious shortcut — unfulfilled units
     * over the units-per-order the last three days happened to average — is an
     * estimate of a number the feed can simply be asked for, and it drifts
     * exactly where it matters: on a group whose bundle mix has changed since.
     *
     * Per group, like every other order count here, because one order carrying
     * two siblings is still one order to pick and cannot be split between them.
     * Deliberately not windowed: unfulfilled is whatever is sitting in those
     * statuses right now, however long ago it was placed.
     */
    private function unfulfilledOrders(): void
    {
        [$itemsByCode, $groupByItem] = $this->unitCodeMaps();

        if (! $itemsByCode) {
            return;
        }

        $seen = [];

        $rows = DB::table('gencys_orders as go')
            ->join('gencys_order_items as goi', 'goi.order_id', '=', 'go.id')
            ->where('go.workspace_id', $this->workspace->id)
            ->whereIn('go.parcel_status', GencysDemandSync::UNFULFILLED_STATUSES)
            ->whereNotNull('goi.sku')
            ->orderBy('go.id')
            ->select('go.id as order_id', 'goi.sku')
            ->cursor();

        foreach ($rows as $row) {
            $components = $itemsByCode[mb_strtoupper(trim((string) $row->sku))] ?? null;

            if ($components === null) {
                continue;
            }

            foreach (array_keys($components) as $item) {
                $group = $groupByItem[$item];

                if (($seen[$group] ?? null) === $row->order_id) {
                    continue;
                }

                $seen[$group] = $row->order_id;
                $this->facts[$group]['unfulfilled_orders_count'] =
                    ($this->facts[$group]['unfulfilled_orders_count'] ?? 0) + 1;
            }
        }
    }

    private function itemsByUnitCode(): array
    {
        $itemBySku = [];
        $groupByItem = [];

        foreach (DB::table('inventory_items')->where('workspace_id', $this->workspace->id)->get(['id', 'parent_id', 'sku']) as $item) {
            $group = (int) ($item->parent_id ?? $item->id);

            // Narrowed here rather than after the scan, so an order line naming
            // only out-of-scope items is skipped before any counting happens.
            if ($this->groupIds !== null && ! in_array($group, $this->groupIds, true)) {
                continue;
            }

            $itemBySku[mb_strtoupper(trim((string) $item->sku))] = (int) $item->id;
            $groupByItem[(int) $item->id] = $group;
        }

        $componentsByCode = [];

        foreach (DB::table('inventory_unit_code_items')->where('workspace_id', $this->workspace->id)->get(['unit_code', 'item_code', 'quantity']) as $component) {
            $item = $itemBySku[mb_strtoupper(trim((string) $component->item_code))] ?? null;

            if ($item === null) {
                continue;
            }

            $code = mb_strtoupper(trim((string) $component->unit_code));
            $componentsByCode[$code][$item] = ($componentsByCode[$code][$item] ?? 0) + (int) $component->quantity;
        }

        $byOrderSku = [];

        foreach (DB::table('inventory_unit_codes')->where('workspace_id', $this->workspace->id)->get(['unit_code', 'sku']) as $unitCode) {
            $components = $componentsByCode[mb_strtoupper(trim((string) $unitCode->unit_code))] ?? null;

            if ($components === null) {
                continue;
            }

            foreach (array_filter([$unitCode->unit_code, $unitCode->sku]) as $key) {
                $byOrderSku[mb_strtoupper(trim((string) $key))] = $components;
            }
        }

        return [$byOrderSku, $groupByItem];
    }

    /**
     * When each group last took stock in and last sent stock out, with the
     * quantity that moved on that day.
     *
     * Purchase-order movements only — receipts and despatches — not RTS returns
     * or write-offs, which say nothing about whether stock is flowing.
     */
    private function movements(): void
    {
        $rows = DB::table('inventory_transactions as t')
            ->join('inventory_items as i', 'i.id', '=', 't.inventory_item_id')
            ->where('i.workspace_id', $this->workspace->id)
            ->when($this->groupIds, fn ($q) => $q->whereIn(DB::raw('COALESCE(i.parent_id, i.id)'), $this->groupIds))
            ->groupByRaw('COALESCE(i.parent_id, i.id), t.date')
            ->selectRaw('COALESCE(i.parent_id, i.id) as group_id, t.date')
            ->selectRaw('SUM(t.po_qty_in) as qty_in, SUM(t.po_qty_out) as qty_out')
            ->havingRaw('qty_in > 0 OR qty_out > 0')
            ->orderBy('t.date')
            ->get();

        // Ordered by date, so the last write for each direction wins and no
        // per-group sorting is needed.
        foreach ($rows as $row) {
            $group = (int) $row->group_id;

            if ((int) $row->qty_in > 0) {
                $this->facts[$group]['last_in_date'] = CarbonImmutable::parse($row->date)->toDateString();
                $this->facts[$group]['last_in_count'] = (int) $row->qty_in;
            }

            if ((int) $row->qty_out > 0) {
                $this->facts[$group]['last_out_date'] = CarbonImmutable::parse($row->date)->toDateString();
                $this->facts[$group]['last_out_count'] = (int) $row->qty_out;
            }
        }
    }

    /**
     * What each group's open purchase orders are doing: the most recent, the
     * one held longest inside the business, the earliest arrival a supplier has
     * committed to, and how much stock is past each of the two order-side
     * targets. The last two feed the bottleneck classification below.
     *
     * Every figure counts the undelivered balance rather than the ordered
     * quantity — a line that is 90% delivered is not still 100% outstanding.
     */
    private function purchaseOrders(): void
    {
        $rows = DB::table('inventory_purchased_order_items as poi')
            ->join('inventory_purchased_orders as po', 'po.id', '=', 'poi.inventory_purchased_order_id')
            ->join('inventory_items as i', 'i.id', '=', 'poi.inventory_item_id')
            ->leftJoin('inventory_purchased_order_item_deliveries as d', 'd.inventory_purchased_order_item_id', '=', 'poi.id')
            ->where('po.workspace_id', $this->workspace->id)
            ->whereIn('po.status', PurchasedOrder::AWAITING_DELIVERY_STATUSES)
            ->whereNotNull('po.issue_date')
            ->when($this->groupIds, fn ($q) => $q->whereIn(DB::raw('COALESCE(i.parent_id, i.id)'), $this->groupIds))
            ->groupBy('poi.id')
            ->selectRaw('poi.id, COALESCE(i.parent_id, i.id) as group_id, po.id as po_id, po.status, po.issue_date, po.expected_delivery_date')
            ->selectRaw('poi.count as ordered, COALESCE(SUM(d.qty), 0) as delivered')
            ->get();

        $today = CarbonImmutable::now()->startOfDay();
        $delayed = [];
        $stocksOverdue = [];     // group => released units past their committed date

        foreach ($rows as $row) {
            $balance = max(0, (int) $row->ordered - (int) $row->delivered);

            // A line that owes nothing is closed, whatever its order's status.
            if ($balance <= 0) {
                continue;
            }

            $group = (int) $row->group_id;
            $issued = CarbonImmutable::parse($row->issue_date)->startOfDay();
            $age = max(0, (int) $issued->diffInDays($today, absolute: false));
            $released = in_array((int) $row->status, PurchasedOrder::RELEASED_STATUSES, true);

            $this->keepLatest($group, 'last_po_date', 'last_po_count', $issued->toDateString(), $balance);
            $this->keepEarliest($group, 'longest_waiting_date', 'longest_waiting_count', $issued->toDateString(), $balance);

            // Raised but never released: the stock is committed on paper and no
            // supplier has been told to start. Reported as the longest such wait.
            if (! $released && $age > ($this->facts[$group]['raised_not_created_days'] ?? -1)) {
                $this->facts[$group]['raised_not_created_days'] = $age;
            }

            if (! $released) {
                $this->facts[$group]['raised_not_created_units'] = ($this->facts[$group]['raised_not_created_units'] ?? 0) + $balance;

                // The slice that has outlived the internal target — what the
                // Payment/Approval bottleneck is weighed on.
            }

            // Read straight off the order. The standing two-week agreement is
            // applied when the order is written, not here — see
            // PurchasedOrder::expectedDeliveryFor — so this is the date the rest
            // of the app sees rather than a second guess at it.
            $expected = $row->expected_delivery_date
                ? CarbonImmutable::parse($row->expected_delivery_date)->toDateString()
                : null;

            if ($expected !== null) {
                $this->keepEarliest($group, 'earliest_expected_date', 'earliest_expected_count', $expected, $balance);
            }

            // Late against the date the order was actually expected, rather than
            // a fixed age: where a supplier has committed to something other
            // than the standing two weeks, that commitment is what they missed.
            // Counted per order, not per line — one purchase order carrying
            // three late lines is one conversation with the supplier.
            if ($released && $expected !== null && $expected < $today->toDateString()) {
                $delayed[$group][(int) $row->po_id] = true;
                $stocksOverdue[$group] = ($stocksOverdue[$group] ?? 0) + $balance;
            }
        }

        foreach ($delayed as $group => $orders) {
            $this->facts[$group]['delayed_po'] = count($orders);
        }

        $this->classifyBottleneck($stocksOverdue);
    }

    /**
     * Name the one thing holding a group up, by working down a fixed order of
     * priority and stopping at the first rule that fires.
     *
     *   1. Warehouse      — orders are owed, stock is on the shelf to fill them,
     *                       and nothing has shipped for more than the target.
     *                       Nobody is waiting on supply; the goods are here.
     *   2. Delayed Stocks — more owed than is on the shelf, and a purchase order
     *                       that would cover it has run past its expected date.
     *                       The supplier is the hold-up.
     *   3. Scaling Item   — more owed than is on the shelf, and demand is running
     *                       above its two-week baseline. Nothing is late; the
     *                       item outgrew its plan.
     *   4. Late PO        — more owed than is on the shelf, and cover is nearly
     *                       out with no late order to blame. Somebody should
     *                       have bought more by now.
     *
     * Priority rather than "whichever pile is biggest", which is what this
     * replaced. The four are different questions, so comparing their unit counts
     * ranked a warehouse holding 500 shippable units above a supplier three
     * weeks late on 400 — when the first is a morning's picking and the second
     * is the reason the shelf is empty. Order says which question to ask first.
     *
     * Rules 2 to 4 share a precondition: more owed than the shelf can fill. That
     * is what separates "we cannot ship this" from rule 1's "we have not". A
     * group past none of these keeps the blank() default of null.
     *
     * @param  array<int, int>  $stocksOverdue  group => units on orders past their expected date
     */
    private function classifyBottleneck(array $stocksOverdue): void
    {
        foreach ($this->bottleneckInputs() as $group => $row) {
            $unfulfilled = $row['unfulfilled'];
            $stock = $row['stock'];

            // 1. Shippable demand standing still.
            if ($unfulfilled > 0 && $stock > 0 && $this->idleDays($group) > self::SHIP_TARGET_DAYS) {
                $this->facts[$group]['bottleneck_stage'] = self::BOTTLENECK_WAREHOUSE;

                continue;
            }

            if ($unfulfilled <= $stock) {
                continue;
            }

            // 2. Supply that was promised and has not come.
            if (($stocksOverdue[$group] ?? 0) > 0) {
                $this->facts[$group]['bottleneck_stage'] = self::BOTTLENECK_STOCKS;

                continue;
            }

            // 3. Selling faster than the plan it was bought against.
            if ($this->trendPercent($group) > self::SCALING_TREND_PCT) {
                $this->facts[$group]['bottleneck_stage'] = self::BOTTLENECK_SCALING;

                continue;
            }

            // 4. Running out with nothing late to explain it.
            if ($row['cover'] !== null && $row['cover'] < self::LATE_PO_COVER_DAYS) {
                $this->facts[$group]['bottleneck_stage'] = self::BOTTLENECK_LATE_PO;
            }
        }
    }

    /**
     * Per-group demand, stock and cover, read from the frozen day.
     *
     * Taken from the snapshot rather than recomputed because these are the same
     * figures the items list shows, and a stage that disagreed with the columns
     * beside it would be unarguable. Today's own row is not written yet when
     * this runs inside a snapshot build, so the latest frozen day stands in —
     * the same stand-in every other backward-looking fact here uses.
     *
     * @return array<int, array{unfulfilled: int, stock: int, cover: float|null}>
     */
    private function bottleneckInputs(): array
    {
        $latest = DB::table('inventory_item_snapshots')
            ->where('workspace_id', $this->workspace->id)
            ->max('snapshot_date');

        if (! $latest) {
            return [];
        }

        $rows = DB::table('inventory_item_snapshots as s')
            ->where('s.workspace_id', $this->workspace->id)
            ->where('s.snapshot_date', $latest)
            ->when($this->groupIds, fn ($q) => $q->whereIn(DB::raw('COALESCE(s.parent_id, s.inventory_item_id)'), $this->groupIds))
            ->groupByRaw('COALESCE(s.parent_id, s.inventory_item_id)')
            ->selectRaw('COALESCE(s.parent_id, s.inventory_item_id) as group_id')
            ->selectRaw('SUM(s.unfulfilled_count) as unfulfilled')
            // "Remaining Qty" on the list: what is physically on the shelf.
            ->selectRaw('SUM(s.current_stocks) as stock')
            ->selectRaw('SUM(s.remaining_after_fulfillment) as remaining')
            ->selectRaw('SUM(s.units_3d) as units_3d')
            ->get();

        $inputs = [];

        foreach ($rows as $row) {
            $average = ((float) $row->units_3d) / self::ITEM_WINDOW;

            $inputs[(int) $row->group_id] = [
                'unfulfilled' => (int) round((float) $row->unfulfilled),
                'stock' => (int) round((float) $row->stock),
                // Null where nothing is selling: cover is not zero then, it is
                // unmeasurable, and a zero would read as an emergency.
                'cover' => $average > 0 ? ((float) $row->remaining) / $average : null,
            ];
        }

        return $inputs;
    }

    /**
     * Days since the group last shipped, measured against the ledger's own last
     * day rather than today: the feed lands in batches, so counted from today a
     * feed that paused on Friday reports the whole warehouse asleep since
     * Friday. Null where the group has never shipped — unknown, not idle.
     */
    private function idleDays(int $group): ?int
    {
        $lastOut = $this->facts[$group]['last_out_date'] ?? null;

        if ($lastOut === null || $this->ledgerAsOf() === null) {
            return null;
        }

        return max(0, (int) CarbonImmutable::parse($lastOut)->startOfDay()
            ->diffInDays($this->ledgerAsOf(), absolute: false));
    }

    /** The ledger's own last day, memoised. */
    private function ledgerAsOf(): ?CarbonImmutable
    {
        if ($this->ledgerAsOf !== null) {
            return $this->ledgerAsOf;
        }

        $asOf = DB::table('inventory_transactions')
            ->where('workspace_id', $this->workspace->id)
            ->max('date');

        return $this->ledgerAsOf = $asOf ? CarbonImmutable::parse($asOf)->startOfDay() : null;
    }

    /**
     * The group's 3-day demand rate as a percentage of its 14-day one — the same
     * figure the list's Trend column renders. 100 is flat; nothing to compare
     * against reads as flat rather than as a spike.
     */
    private function trendPercent(int $group): float
    {
        $recent = (float) ($this->facts[$group]['units_3d'] ?? 0);
        $baseline = (float) ($this->facts[$group]['units_14d'] ?? 0);

        if ($baseline <= 0) {
            return 100.0;
        }

        return (100 * ($recent / 3)) / ($baseline / 14);
    }

    /** Keep the later of two dated figures, carrying its count with it. */
    private function keepLatest(int $group, string $dateKey, string $countKey, string $date, int $count): void
    {
        $current = $this->facts[$group][$dateKey] ?? null;

        if ($current === null || $date > $current) {
            $this->facts[$group][$dateKey] = $date;
            $this->facts[$group][$countKey] = $count;
        } elseif ($date === $current) {
            $this->facts[$group][$countKey] = ($this->facts[$group][$countKey] ?? 0) + $count;
        }
    }

    /** Keep the earlier of two dated figures, carrying its count with it. */
    private function keepEarliest(int $group, string $dateKey, string $countKey, string $date, int $count): void
    {
        $current = $this->facts[$group][$dateKey] ?? null;

        if ($current === null || $date < $current) {
            $this->facts[$group][$dateKey] = $date;
            $this->facts[$group][$countKey] = $count;
        } elseif ($date === $current) {
            $this->facts[$group][$countKey] = ($this->facts[$group][$countKey] ?? 0) + $count;
        }
    }
}
