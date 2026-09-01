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
        'last_in_date', 'last_in_count', 'last_out_date', 'last_out_count',
        'last_po_date', 'last_po_count', 'raised_not_created_days', 'raised_not_created_units',
        'earliest_expected_date', 'earliest_expected_count', 'longest_waiting_date',
        'longest_waiting_count', 'delayed_po', 'bottleneck_stage',
    ];

    /**
     * Thresholds behind the bottleneck classification, mirrored from
     * PurchaseOrderFlowController so the per-item label agrees with the
     * dashboard flow panel: an item reading "Delay in stocks" here is counting
     * the same stock the Supplier card is.
     */
    private const APPROVAL_SLA_DAYS = 7;

    private const SHIP_TARGET_DAYS = 2;

    /**
     * Late PO looks back this many days and fires only once a reorder need has
     * outlived the grace period with no purchase order raised against it — a
     * need that appears for a day or two is normal reaction time, not a miss.
     */
    private const LATE_PO_WINDOW_DAYS = 14;

    private const LATE_PO_GRACE_DAYS = 3;

    /** The four owners a hold-up can belong to. */
    private const BOTTLENECK_LATE_PO = 'Late PO';

    private const BOTTLENECK_APPROVAL = 'Delay in Payment / Approval';

    private const BOTTLENECK_STOCKS = 'Delay in stocks';

    private const BOTTLENECK_WAREHOUSE = 'Delay in warehouse';

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
    public function __construct(private Workspace $workspace, private ?array $groupIds = null)
    {
        $this->demandWindows();
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

        [$itemsByCode, $groupByItem] = $this->itemsByUnitCode();

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
        $approvalOverdue = [];   // group => un-released units sat past the internal target
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
                if ($age > self::APPROVAL_SLA_DAYS) {
                    $approvalOverdue[$group] = ($approvalOverdue[$group] ?? 0) + $balance;
                }
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

        $this->classifyBottleneck($approvalOverdue, $stocksOverdue);
    }

    /**
     * Reduce every hold-up on a group to one of four owners and keep the one
     * gripping the most units:
     *
     *   Late PO                     — a reorder need nobody has raised a PO for.
     *   Delay in Payment / Approval — a raised PO stuck in our own queue.
     *   Delay in stocks             — a released PO the supplier is late on.
     *   Delay in warehouse          — shippable stock sitting unshipped.
     *
     * The four are deliberately different questions, so "biggest" compares units
     * stuck rather than like against like, and the label names wherever the
     * largest pile happens to sit. A group with nothing past any target keeps
     * the blank() default of null.
     *
     * @param  array<int, int>  $approvalOverdue  group => un-released units past the SLA
     * @param  array<int, int>  $stocksOverdue  group => released units past their date
     */
    private function classifyBottleneck(array $approvalOverdue, array $stocksOverdue): void
    {
        $latePo = $this->latePurchaseOrders();
        $warehouse = $this->warehouseIdle();

        $groups = array_unique(array_merge(
            array_keys($latePo),
            array_keys($approvalOverdue),
            array_keys($stocksOverdue),
            array_keys($warehouse),
        ));

        foreach ($groups as $group) {
            $candidates = [
                self::BOTTLENECK_LATE_PO => $latePo[$group] ?? 0,
                self::BOTTLENECK_APPROVAL => $approvalOverdue[$group] ?? 0,
                self::BOTTLENECK_STOCKS => $stocksOverdue[$group] ?? 0,
                self::BOTTLENECK_WAREHOUSE => $warehouse[$group] ?? 0,
            ];

            arsort($candidates);
            $top = array_key_first($candidates);

            if ($candidates[$top] > 0) {
                $this->facts[$group]['bottleneck_stage'] = $top;
            }
        }
    }

    /**
     * Groups whose reorder need has outlived the grace period with no purchase
     * order raised against it — the PO officer has not acted when the numbers
     * told them to. Returns group => today's uncovered units, the figure the
     * classifier ranks it by; a group that is not late is absent.
     *
     * Read from the frozen snapshots, not recomputed live: po_needed is stored
     * per day, so "needed on more than three of the last fourteen days" is a
     * question only the history can answer. Today's own row is not written yet
     * when this runs inside a snapshot build, so the latest frozen day stands in
     * for it — a day's lag on a fourteen-day judgement.
     *
     * po_needed is rolled to the group the same way the items list rolls it:
     * lead time and coverage are the parent's when it has one else the group
     * max, demand and incoming sum. Summing the stored per-item po_needed
     * instead would count the group's buffer once per sibling.
     *
     * @return array<int, int>
     */
    private function latePurchaseOrders(): array
    {
        $latest = DB::table('inventory_item_snapshots')
            ->where('workspace_id', $this->workspace->id)
            ->max('snapshot_date');

        if (! $latest) {
            return [];
        }

        $windowStart = CarbonImmutable::parse($latest)->subDays(self::LATE_PO_WINDOW_DAYS)->toDateString();

        $lead = 'COALESCE(MAX(CASE WHEN s.is_parent = 1 THEN s.lead_time END), MAX(s.lead_time))';
        $cover = 'COALESCE(MAX(CASE WHEN s.is_parent = 1 THEN s.days_of_coverage END), MAX(s.days_of_coverage))';
        $avg = 'SUM(s.three_days_average)';
        $remaining = 'COALESCE(SUM(s.remaining_after_fulfillment), 0)';
        $needed = "GREATEST(0, ($cover * $avg) + ($lead * $avg) - $remaining)";

        $rows = DB::table('inventory_item_snapshots as s')
            ->where('s.workspace_id', $this->workspace->id)
            ->where('s.snapshot_date', '>', $windowStart)
            ->when($this->groupIds, fn ($q) => $q->whereIn(DB::raw('COALESCE(s.parent_id, s.inventory_item_id)'), $this->groupIds))
            ->groupByRaw('s.snapshot_date, COALESCE(s.parent_id, s.inventory_item_id)')
            ->selectRaw('s.snapshot_date, COALESCE(s.parent_id, s.inventory_item_id) as group_id')
            ->selectRaw("$needed as needed")
            ->get();

        $daysNeeded = [];
        $todayNeeded = [];

        foreach ($rows as $row) {
            $group = (int) $row->group_id;

            if ((float) $row->needed > 0) {
                $daysNeeded[$group] = ($daysNeeded[$group] ?? 0) + 1;
            }

            if ((string) $row->snapshot_date === (string) $latest) {
                $todayNeeded[$group] = (int) round((float) $row->needed);
            }
        }

        $raisedInWindow = $this->groupsWithPoRaisedSince($windowStart);

        $late = [];

        foreach ($daysNeeded as $group => $days) {
            // Persisted past the grace period, still needed today, and no PO
            // raised while it went uncovered: the officer is late on this one.
            if ($days > self::LATE_PO_GRACE_DAYS
                && ! isset($raisedInWindow[$group])
                && ($todayNeeded[$group] ?? 0) > 0) {
                $late[$group] = $todayNeeded[$group];
            }
        }

        return $late;
    }

    /**
     * Groups with any purchase order — of any status — raised since the given
     * date, as a set keyed by group id. A PO raised recently means the officer
     * acted, so the group is not "late" however much the maths still wants; the
     * leftover gap belongs to whichever stage that order is now sitting in.
     *
     * @return array<int, true>
     */
    private function groupsWithPoRaisedSince(string $since): array
    {
        $rows = DB::table('inventory_purchased_orders as po')
            ->join('inventory_purchased_order_items as poi', 'poi.inventory_purchased_order_id', '=', 'po.id')
            ->join('inventory_items as i', 'i.id', '=', 'poi.inventory_item_id')
            ->where('po.workspace_id', $this->workspace->id)
            ->whereNotNull('po.issue_date')
            ->where('po.issue_date', '>', $since)
            ->when($this->groupIds, fn ($q) => $q->whereIn(DB::raw('COALESCE(i.parent_id, i.id)'), $this->groupIds))
            ->groupByRaw('COALESCE(i.parent_id, i.id)')
            ->selectRaw('COALESCE(i.parent_id, i.id) as group_id')
            ->pluck('group_id');

        $set = [];

        foreach ($rows as $group) {
            $set[(int) $group] = true;
        }

        return $set;
    }

    /**
     * Groups sitting on shippable stock that has stopped moving. Shippable is
     * stock matched by unmet demand — min(on hand, unfulfilled) per SKU, summed
     * to the group — so a shelf full of the wrong variant does not count. Idle
     * is measured from the last despatch against the ledger's own last day,
     * never today: the feed lands in batches, so counted from today a feed that
     * paused on Friday reports the whole warehouse asleep since Friday.
     *
     * Returns group => shippable units for groups idle at least the target. A
     * group that has never shipped is absent — unknown, not idle.
     *
     * @return array<int, int>
     */
    private function warehouseIdle(): array
    {
        $latest = DB::table('inventory_item_snapshots')
            ->where('workspace_id', $this->workspace->id)
            ->max('snapshot_date');

        $asOf = DB::table('inventory_transactions')
            ->where('workspace_id', $this->workspace->id)
            ->max('date');

        if (! $latest || ! $asOf) {
            return [];
        }

        $asOf = CarbonImmutable::parse($asOf)->startOfDay();

        $rows = DB::table('inventory_item_snapshots as s')
            ->where('s.workspace_id', $this->workspace->id)
            ->where('s.snapshot_date', $latest)
            ->when($this->groupIds, fn ($q) => $q->whereIn(DB::raw('COALESCE(s.parent_id, s.inventory_item_id)'), $this->groupIds))
            ->groupByRaw('COALESCE(s.parent_id, s.inventory_item_id)')
            ->selectRaw('COALESCE(s.parent_id, s.inventory_item_id) as group_id')
            ->selectRaw('SUM(GREATEST(0, LEAST(s.current_stocks, s.unfulfilled_count))) as here')
            ->get();

        $idle = [];

        foreach ($rows as $row) {
            $here = (int) round((float) $row->here);

            if ($here <= 0) {
                continue;
            }

            $group = (int) $row->group_id;
            $lastOut = $this->facts[$group]['last_out_date'] ?? null;

            // Never shipped is unknown, not idle — a brand new SKU has not
            // stalled, it has not started.
            if ($lastOut === null) {
                continue;
            }

            $idleDays = max(0, (int) CarbonImmutable::parse($lastOut)->startOfDay()->diffInDays($asOf, absolute: false));

            if ($idleDays >= self::SHIP_TARGET_DAYS) {
                $idle[$group] = $here;
            }
        }

        return $idle;
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
