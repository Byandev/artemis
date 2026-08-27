<?php

namespace Modules\Finance\Services;

use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Models\TransactionType;
use Modules\GencysERP\Models\GencysDailySalesOrder;
use Modules\GencysERP\Models\Intern;
use Modules\GencysERP\Support\InternResolver;

/**
 * Builds, saves and reads the per-user slices of an income statement — the same
 * figures as {@see ProductIncomeStatementService}, cut by who sold rather than
 * by what was sold.
 *
 * An order belongs to one intern, so nothing has to be split: its `intern_brands_name`
 * cell is resolved to an intern and then to that intern's linked user, and the
 * whole order's money goes there. Orders whose cell resolves to nobody roll into
 * a single "Unassigned" row.
 *
 * The costs that aren't carried on the order itself — ad spend, bought goods and
 * the freight on them — come from the charge-to shares on finance transactions,
 * which is the user-side counterpart of the product tags the product statement
 * reads.
 *
 * The result is snapshotted into `finance_income_user_statements` when the parent
 * statement is saved or regenerated. A first view with no snapshot builds one.
 */
class UserIncomeStatementService
{
    /** gencys_orders.parcel_status value that counts as delivered revenue. */
    private const DELIVERED_STATUS = 'DELIVERED';

    /** Platforms whose orders never belong on the statement. */
    private const EXCLUDED_PLATFORMS = ['Shopee', 'TikTok'];

    /** Pages whose orders never belong on the statement. */
    private const EXCLUDED_PAGE_LIKE = '%pikutin%';

    /** (Re)compute and store every per-user row for the statement's month. */
    public function snapshot(IncomeStatement $statement): void
    {
        $workspace = $statement->workspace;
        [$from, $to] = $this->range($statement);

        // Struck at the rates saved on the parent statement, not today's.
        $codRate = (float) $statement->cod_fee_rate;
        $vatRate = (float) $statement->vat_rate;

        $resolve = $this->cellUserResolver($workspace);

        $totals = [];
        $blank = [
            'delivered_orders' => 0, 'delivered_units' => 0, 'delivered_amount' => 0.0,
            'shipped_orders' => 0, 'total_shipping_fee' => 0.0,
            'ad_spent' => 0.0, 'total_bought_cogs' => 0.0,
            'total_bought_cogs_delivery_fee' => 0.0, 'total_delivered_cogs' => 0.0,
        ];

        // Cells are folded into users here rather than in SQL — the mapping runs
        // through the intern resolver, which is PHP.
        foreach ($this->deliveredByCell($workspace, $from, $to) as $cell => $row) {
            $key = (string) ($resolve($cell) ?? '');
            $totals[$key] ??= $blank;
            $totals[$key]['delivered_orders'] += $row['orders'];
            $totals[$key]['delivered_amount'] += $row['revenue'];
            $totals[$key]['total_delivered_cogs'] += $row['cog'];
        }

        foreach ($this->deliveredUnitsByCell($workspace, $from, $to) as $cell => $units) {
            $key = (string) ($resolve($cell) ?? '');
            $totals[$key] ??= $blank;
            $totals[$key]['delivered_units'] += $units;
        }

        foreach ($this->shippedByCell($workspace, $from, $to) as $cell => $row) {
            $key = (string) ($resolve($cell) ?? '');
            $totals[$key] ??= $blank;
            $totals[$key]['shipped_orders'] += $row['orders'];
            $totals[$key]['total_shipping_fee'] += $row['shipping'];
        }

        // Charged costs are already per user, so they key in directly.
        $charged = [
            'ad_spent' => $this->chargedTotalsForTypes($workspace, $from, $to, $this->adSpentTypeIds($workspace)),
            'total_bought_cogs' => $this->chargedTotalsForTypes($workspace, $from, $to, $this->costOfGoodsTypeIds($workspace)),
            'total_bought_cogs_delivery_fee' => $this->chargedTotalsForTypes($workspace, $from, $to, $this->cogDeliveryTypeIds($workspace)),
        ];

        foreach ($charged as $field => $amounts) {
            foreach ($amounts as $key => $amount) {
                $totals[(string) $key] ??= $blank;
                $totals[(string) $key][$field] += $amount;
            }
        }

        $names = User::whereIn('id', array_values(array_filter(array_keys($totals), fn ($k) => $k !== '')))
            ->pluck('name', 'id');

        $rows = collect($totals)->map(function ($t, $key) use ($names, $codRate, $vatRate) {
            $userId = $key === '' ? null : (int) $key;

            $revenue = round((float) $t['delivered_amount'], 2);
            $codFee = round($revenue * $codRate, 2);
            $codVat = round($codFee * $vatRate, 2);

            $adSpent = round((float) $t['ad_spent'], 2);
            $shippingFee = round((float) $t['total_shipping_fee'], 2);
            $deliveredCogs = round((float) $t['total_delivered_cogs'], 2);
            $boughtCogs = round((float) $t['total_bought_cogs'], 2);
            $boughtFreight = round((float) $t['total_bought_cogs_delivery_fee'], 2);

            // Both margins take the same costs off delivered revenue and differ
            // only in which cost of goods they charge.
            $commonCosts = $adSpent + $shippingFee + $codFee + $codVat;

            return [
                'user_id' => $userId,
                'user_name' => $userId !== null ? ($names[$userId] ?? 'Unknown') : 'Unassigned',
                'delivered_orders' => (int) $t['delivered_orders'],
                'delivered_units' => (int) $t['delivered_units'],
                'delivered_amount' => $revenue,
                'shipped_orders' => (int) $t['shipped_orders'],
                'total_shipping_fee' => $shippingFee,
                'ad_spent' => $adSpent,
                'cod_fee' => $codFee,
                'cod_fee_vat' => $codVat,
                'total_bought_cogs' => $boughtCogs,
                'total_bought_cogs_delivery_fee' => $boughtFreight,
                'total_delivered_cogs' => $deliveredCogs,
                'gross_profit_delivered_cogs' => round($revenue - $commonCosts - $deliveredCogs, 2),
                // Freight on a purchase is part of what the stock cost.
                'gross_profit_bought_cogs' => round($revenue - $commonCosts - $boughtCogs - $boughtFreight, 2),
            ];
        })->values();

        DB::transaction(function () use ($statement, $rows) {
            $statement->userStatements()->delete();

            foreach ($rows as $row) {
                $statement->userStatements()->create($row);
            }
        });
    }

    /**
     * The saved per-user rows — biggest delivered first, with the unassigned row
     * last — plus a Total across the named users. Built on first access.
     *
     * @return array{users: list<array<string, mixed>>, total: array<string, mixed>, unassigned: list<array<string, mixed>>, rates: array{cod:float, vat:float}}
     */
    public function payload(IncomeStatement $statement): array
    {
        $this->ensureSnapshot($statement);

        $rows = $statement->userStatements()->get()->map(fn ($r) => [
            'user_id' => $r->user_id,
            'user' => $r->user_name ?: 'Unassigned',
            'delivered_orders' => (int) $r->delivered_orders,
            'delivered_units' => (int) $r->delivered_units,
            'delivered_amount' => (float) $r->delivered_amount,
            'shipped_orders' => (int) $r->shipped_orders,
            'total_shipping_fee' => (float) $r->total_shipping_fee,
            'ad_spent' => (float) $r->ad_spent,
            'cod_fee' => (float) $r->cod_fee,
            'cod_fee_vat' => (float) $r->cod_fee_vat,
            'total_bought_cogs' => (float) $r->total_bought_cogs,
            'total_bought_cogs_delivery_fee' => (float) $r->total_bought_cogs_delivery_fee,
            'total_delivered_cogs' => (float) $r->total_delivered_cogs,
            'gross_profit_delivered_cogs' => (float) $r->gross_profit_delivered_cogs,
            'gross_profit_bought_cogs' => (float) $r->gross_profit_bought_cogs,
        ]);

        $named = $rows->filter(fn ($r) => $r['user_id'] !== null)
            ->sortByDesc('delivered_amount')
            ->values();

        $sum = fn (string $key) => round($named->sum($key), 2);

        // The total covers the named users; revenue nobody is credited with is
        // not anyone's, so counting it would overstate every column.
        $total = [
            'user_id' => null,
            'user' => 'Total',
            'delivered_orders' => (int) $named->sum('delivered_orders'),
            'delivered_units' => (int) $named->sum('delivered_units'),
            'delivered_amount' => $sum('delivered_amount'),
            'shipped_orders' => (int) $named->sum('shipped_orders'),
            'total_shipping_fee' => $sum('total_shipping_fee'),
            'ad_spent' => $sum('ad_spent'),
            'cod_fee' => $sum('cod_fee'),
            'cod_fee_vat' => $sum('cod_fee_vat'),
            'total_bought_cogs' => $sum('total_bought_cogs'),
            'total_bought_cogs_delivery_fee' => $sum('total_bought_cogs_delivery_fee'),
            'total_delivered_cogs' => $sum('total_delivered_cogs'),
            'gross_profit_delivered_cogs' => $sum('gross_profit_delivered_cogs'),
            'gross_profit_bought_cogs' => $sum('gross_profit_bought_cogs'),
        ];

        $unassigned = $rows->first(fn ($r) => $r['user_id'] === null);

        return [
            'users' => ($unassigned ? $named->push($unassigned) : $named)->values()->all(),
            'total' => $total,
            // What the Unassigned row is made of, so it can be opened up.
            'unassigned' => $this->unassignedBreakdown($statement),
            'rates' => [
                'cod' => (float) $statement->cod_fee_rate,
                'vat' => (float) $statement->vat_rate,
            ],
        ];
    }

    /**
     * The intern cells sitting in the Unassigned row — the names written on the
     * orders that resolve to no user, biggest first.
     *
     * Computed live rather than snapshotted. It is a to-do list for whoever
     * links interns to users, and it should shrink as they work through it.
     *
     * @return list<array{cell:?string, delivered_orders:int, delivered_amount:float, shipped_orders:int, shipping_fee:float}>
     */
    public function unassignedBreakdown(IncomeStatement $statement): array
    {
        $workspace = $statement->workspace;
        [$from, $to] = $this->range($statement);
        $resolve = $this->cellUserResolver($workspace);

        $rows = [];

        foreach ($this->deliveredByCell($workspace, $from, $to) as $cell => $row) {
            if ($resolve($cell) !== null) {
                continue;
            }
            $rows[$cell] = [
                'cell' => $cell === '' ? null : $cell,
                'delivered_orders' => $row['orders'],
                'delivered_amount' => $row['revenue'],
                'shipped_orders' => 0,
                'shipping_fee' => 0.0,
            ];
        }

        foreach ($this->shippedByCell($workspace, $from, $to) as $cell => $row) {
            if ($resolve($cell) !== null) {
                continue;
            }
            $rows[$cell] ??= [
                'cell' => $cell === '' ? null : $cell,
                'delivered_orders' => 0,
                'delivered_amount' => 0.0,
                'shipped_orders' => 0,
                'shipping_fee' => 0.0,
            ];
            $rows[$cell]['shipped_orders'] = $row['orders'];
            $rows[$cell]['shipping_fee'] = $row['shipping'];
        }

        usort($rows, fn ($a, $b) => [$b['delivered_amount'], $b['shipping_fee']] <=> [$a['delivered_amount'], $a['shipping_fee']]);

        return $rows;
    }

    private function ensureSnapshot(IncomeStatement $statement): void
    {
        if (! $statement->userStatements()->exists()) {
            $this->snapshot($statement);
        }
    }

    /** Orders that belong on the statement at all, before any date scoping. */
    private function orders(Workspace $workspace)
    {
        return GencysDailySalesOrder::where('gencys_orders.workspace_id', $workspace->id)
            ->whereNotIn('gencys_orders.platform', self::EXCLUDED_PLATFORMS)
            ->whereNotLike('gencys_orders.page', self::EXCLUDED_PAGE_LIKE);
    }

    /**
     * Delivered parcels, revenue and cost of goods per intern cell. An order
     * belongs to one cell, so nothing is split.
     *
     * @return array<string, array{orders:int, revenue:float, cog:float}>
     */
    private function deliveredByCell(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        return $this->orders($workspace)
            ->where('gencys_orders.parcel_status', self::DELIVERED_STATUS)
            ->whereBetween('gencys_orders.parcel_updated_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('gencys_orders.intern_brands_name as cell')
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('COALESCE(SUM(gencys_orders.price_final), 0) as revenue')
            ->selectRaw('COALESCE(SUM(gencys_orders.total_cog), 0) as cog')
            ->groupBy('cell')
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->cell => [
                'orders' => (int) $r->orders,
                'revenue' => round((float) $r->revenue, 2),
                'cog' => round((float) $r->cog, 2),
            ]])
            ->all();
    }

    /**
     * Pieces delivered per intern cell, from the line-item quantities.
     *
     * @return array<string, int>
     */
    private function deliveredUnitsByCell(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        return $this->orders($workspace)
            ->where('gencys_orders.parcel_status', self::DELIVERED_STATUS)
            ->whereBetween('gencys_orders.parcel_updated_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->join('gencys_order_items as goi', 'goi.order_id', '=', 'gencys_orders.id')
            ->selectRaw('gencys_orders.intern_brands_name as cell')
            ->selectRaw('COALESCE(SUM(GREATEST(COALESCE(goi.quantity, 1), 1)), 0) as units')
            ->groupBy('cell')
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->cell => (int) $r->units])
            ->all();
    }

    /**
     * Parcels shipped out and the courier fee on them, per intern cell —
     * whatever became of them, since the courier is paid for a return too.
     *
     * @return array<string, array{orders:int, shipping:float}>
     */
    private function shippedByCell(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        return $this->orders($workspace)
            ->whereBetween('gencys_orders.shipped_out_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('gencys_orders.intern_brands_name as cell')
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('COALESCE(SUM(gencys_orders.shipping_fee), 0) as shipping')
            ->groupBy('cell')
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->cell => [
                'orders' => (int) $r->orders,
                'shipping' => round((float) $r->shipping, 2),
            ]])
            ->all();
    }

    /**
     * Charge-to totals per user for the given transaction types — the user-side
     * counterpart of the product tags the product statement reads. A transaction
     * split across several people contributes each person's share.
     *
     * @param  list<int>  $typeIds
     * @return array<int, float>
     */
    private function chargedTotalsForTypes(Workspace $workspace, Carbon $from, Carbon $to, array $typeIds): array
    {
        if (empty($typeIds)) {
            return [];
        }

        return DB::table('finance_transaction_charge_to as ct')
            ->join('finance_transactions as t', 't.id', '=', 'ct.transaction_id')
            ->where('t.workspace_id', $workspace->id)
            ->where('t.type', 'out')
            ->whereIn('t.transaction_type_id', $typeIds)
            ->whereBetween('t.date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('ct.user_id')
            ->selectRaw('ct.user_id, SUM(ct.amount) as amount')
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->user_id => round((float) $r->amount, 2)])
            ->all();
    }

    /**
     * A memoized intern-cell → user_id resolver for the workspace (null =
     * the cell matches no intern, or that intern has no linked user).
     */
    private function cellUserResolver(Workspace $workspace): callable
    {
        $resolver = new InternResolver($workspace->id);
        $interns = Intern::where('workspace_id', $workspace->id)->get(['id', 'user_id'])->keyBy('id');
        $cache = [];

        return function (?string $cell) use ($resolver, $interns, &$cache): ?int {
            $key = (string) $cell;

            if (! array_key_exists($key, $cache)) {
                $internId = $resolver->resolve($cell);
                $cache[$key] = $internId ? ($interns[$internId]->user_id ?? null) : null;
            }

            return $cache[$key];
        };
    }

    /** @return list<int> */
    private function adSpentTypeIds(Workspace $workspace): array
    {
        return $this->typeIdsMatching($workspace, ['%adspent%', '%ad spent%', '%ad spend%']);
    }

    /** @return list<int> */
    private function costOfGoodsTypeIds(Workspace $workspace): array
    {
        return $this->typeIdsMatching($workspace, ['%cost of goods%']);
    }

    /** @return list<int> */
    private function cogDeliveryTypeIds(Workspace $workspace): array
    {
        return $this->typeIdsMatching($workspace, ['%delivery of cog%', '%delivery of goods%', '%cog delivery%']);
    }

    /**
     * @param  list<string>  $patterns
     * @return list<int>
     */
    private function typeIdsMatching(Workspace $workspace, array $patterns): array
    {
        return TransactionType::where('workspace_id', $workspace->id)
            ->where(function ($q) use ($patterns) {
                foreach ($patterns as $pattern) {
                    $q->orWhereRaw('LOWER(name) LIKE ?', [$pattern]);
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return array{0:Carbon, 1:Carbon} [from, to] for the statement's month. */
    private function range(IncomeStatement $statement): array
    {
        $start = $statement->period_month->copy()->startOfMonth();

        return [$start, $start->copy()->endOfMonth()];
    }
}
