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
        'orders_3d', 'units_3d', 'orders_7d', 'units_7d', 'orders_14d', 'units_14d',
        'last_in_date', 'last_in_count', 'last_out_date', 'last_out_count',
        'last_po_date', 'last_po_count', 'raised_not_created_days', 'raised_not_created_units',
        'earliest_expected_date', 'earliest_expected_count', 'longest_waiting_date',
        'longest_waiting_count', 'delayed_po', 'bottleneck_stage',
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

        $groupsByCode = $this->groupsByUnitCode();

        if (! $groupsByCode) {
            return;
        }

        $starts = [];

        foreach (self::WINDOWS as $days) {
            $starts[$days] = $this->demandAsOf->subDays($days)->startOfDay();
        }

        $widest = min($starts);
        $units = [];
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
            $components = $groupsByCode[mb_strtoupper(trim((string) $row->sku))] ?? null;

            if ($components === null) {
                continue;
            }

            $orderedAt = CarbonImmutable::parse($row->order_date);

            foreach (self::WINDOWS as $days) {
                if ($orderedAt < $starts[$days]) {
                    continue;
                }

                foreach ($components as $group => $perBundle) {
                    $units[$days][$group] = ($units[$days][$group] ?? 0) + $perBundle;

                    if (($lastOrder[$days][$group] ?? null) !== $row->order_id) {
                        $lastOrder[$days][$group] = $row->order_id;
                        $orders[$days][$group] = ($orders[$days][$group] ?? 0) + 1;
                    }
                }
            }
        }

        foreach (self::WINDOWS as $days) {
            foreach ($units[$days] ?? [] as $group => $total) {
                $this->facts[$group]["units_{$days}d"] = $total;
                $this->facts[$group]["orders_{$days}d"] = $orders[$days][$group] ?? 0;
            }
        }
    }

    /**
     * Normalised order-line sku => [group id => units per bundle].
     *
     * Keyed by both the unit code's label and its own sku, because an order line
     * names it by either — the same pair SyncInventoryFromGencysOrders accepts.
     * Components landing on the same group are summed: a bundle holding two
     * variants of one product is that many units against the group's supply.
     *
     * @return array<string, array<int, int>>
     */
    private function groupsByUnitCode(): array
    {
        $groupBySku = [];

        foreach (DB::table('inventory_items')->where('workspace_id', $this->workspace->id)->get(['id', 'parent_id', 'sku']) as $item) {
            $group = (int) ($item->parent_id ?? $item->id);

            // Narrowed here rather than after the scan, so an order line naming
            // only out-of-scope items is skipped before any counting happens.
            if ($this->groupIds !== null && ! in_array($group, $this->groupIds, true)) {
                continue;
            }

            $groupBySku[mb_strtoupper(trim((string) $item->sku))] = $group;
        }

        $componentsByCode = [];

        foreach (DB::table('inventory_unit_code_items')->where('workspace_id', $this->workspace->id)->get(['unit_code', 'item_code', 'quantity']) as $component) {
            $group = $groupBySku[mb_strtoupper(trim((string) $component->item_code))] ?? null;

            if ($group === null) {
                continue;
            }

            $code = mb_strtoupper(trim((string) $component->unit_code));
            $componentsByCode[$code][$group] = ($componentsByCode[$code][$group] ?? 0) + (int) $component->quantity;
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

        return $byOrderSku;
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
     * committed to, and which stage is holding the most stock.
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
        $stages = [];
        $delayed = [];

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
            }

            $stage = PurchasedOrder::STATUSES[(int) $row->status] ?? (string) $row->status;
            $stages[$group][$stage] = ($stages[$group][$stage] ?? 0) + $balance;
        }

        foreach ($delayed as $group => $orders) {
            $this->facts[$group]['delayed_po'] = count($orders);
        }

        // The stage holding the most undelivered stock — where this group's
        // supply is actually stuck, rather than where the loudest order is.
        foreach ($stages as $group => $byStage) {
            arsort($byStage);
            $this->facts[$group]['bottleneck_stage'] = array_key_first($byStage);
        }
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
