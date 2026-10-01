<?php

namespace Modules\Finance\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Services\Concerns\ClosesOutSlices;
use Modules\Finance\Statements\LossCarryovers;
use Modules\Finance\Statements\OrderTotals;
use Modules\Finance\Statements\ProductCostAllocator;
use Modules\Finance\Statements\StatementOrderSourceFactory;
use Modules\Finance\Statements\TransactionTotals;

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
 * The costs that aren't carried on the order itself reach a user two ways. Ad
 * spend is known per person directly — from the page it ran on, or from the
 * charge-to shares. Bought goods and their freight are tagged to a product
 * instead, so a user takes the share of each product matching their share of
 * its delivered orders; those columns are therefore the sum of this user's rows
 * on the user-and-product statement.
 *
 * The result is snapshotted into `finance_income_user_statements` when the parent
 * statement is saved or regenerated. A first view with no snapshot builds one.
 */
class UserIncomeStatementService
{
    use ClosesOutSlices;

    public function __construct(
        private readonly StatementOrderSourceFactory $sources,
        private readonly TransactionTotals $transactions,
        private readonly LossCarryovers $carryovers,
        private readonly ProductCostAllocator $allocator,
    ) {}

    /** (Re)compute and store every per-user row for the statement's month. */
    public function snapshot(IncomeStatement $statement): void
    {
        $workspace = $statement->workspace;
        [$from, $to] = $this->range($statement);

        // Struck at the rates saved on the parent statement, not today's.
        $codRate = (float) $statement->cod_fee_rate;
        $vatRate = (float) $statement->vat_rate;
        $advisoryRate = (float) $statement->advisory_rate;
        $gencysPartner = (bool) $workspace->is_gencys_partner;

        $source = $this->sources->for($workspace);
        $totals = $source->totalsByUser($workspace, $from, $to);

        // Goods bought and their freight are tagged to a product, not to a
        // person, so a user's figure is their share of every product they
        // moved — the per-product-per-user allocation added up along the
        // product axis. That makes this column the sum of the same user's rows
        // on the user-and-product statement, by construction rather than by
        // coincidence.
        $allocated = $this->allocator->byUser($this->allocator->allocate(
            [
                'total_bought_cogs' => $this->transactions->byProductTag($workspace, $from, $to, TransactionTotals::COST_OF_GOODS, withUntagged: true),
                'total_bought_cogs_delivery_fee' => $this->transactions->byProductTag($workspace, $from, $to, TransactionTotals::COG_DELIVERY, withUntagged: true),
            ],
            $source->totalsByUserProduct($workspace, $from, $to),
        ));

        // Ad spend stays per person: it is known directly, from the page it ran
        // on (whose owner is the seller) or from the charge-to shares.
        $charged = [
            'ad_spent' => $source->adSpendByUser($workspace, $from, $to),
            'total_bought_cogs' => $allocated['total_bought_cogs'] ?? [],
            'total_bought_cogs_delivery_fee' => $allocated['total_bought_cogs_delivery_fee'] ?? [],
        ];

        // A user earns a row for a charged cost even with no orders behind it.
        foreach ($charged as $amounts) {
            foreach (array_keys($amounts) as $key) {
                $totals[(string) $key] ??= OrderTotals::empty();
            }
        }

        // And for a deficit carried in. Someone who owes from last month has to
        // appear even having sold nothing this one, or what they owe quietly
        // disappears the month they stop selling — and reappears if they start
        // again, which is worse.
        $carried = $this->carryovers->forUsers($workspace, $from);

        foreach (array_keys($carried) as $key) {
            $totals[(string) $key] ??= OrderTotals::empty();
        }

        $names = User::whereIn('id', array_values(array_filter(array_keys($totals), fn ($k) => $k !== '')))
            ->pluck('name', 'id');

        $rows = collect($totals)->map(function (OrderTotals $t, $key) use ($names, $charged, $codRate, $vatRate, $advisoryRate, $gencysPartner) {
            $userId = $key === '' ? null : (int) $key;

            $revenue = round($t->deliveredAmount, 2);
            $codFee = round($revenue * $codRate, 2);
            $codVat = round($codFee * $vatRate, 2);

            $adSpent = round((float) ($charged['ad_spent'][(int) $key] ?? 0), 2);
            $shippingFee = round($t->shippingFee, 2);
            $deliveredCogs = round($t->deliveredCogs, 2);
            // Keyed by $key rather than (int) $key: the allocation puts costs
            // for a product nobody delivered under '', which would cast to 0.
            $boughtCogs = round((float) ($charged['total_bought_cogs'][$key] ?? 0), 2);
            $boughtFreight = round((float) ($charged['total_bought_cogs_delivery_fee'][$key] ?? 0), 2);

            // Both margins take the same costs off delivered revenue and differ
            // only in which cost of goods they charge.
            $commonCosts = $adSpent + $shippingFee + $codFee + $codVat;

            $grossDelivered = round($revenue - $commonCosts - $deliveredCogs, 2);
            // Freight on a purchase is part of what the stock cost.
            $grossBought = round($revenue - $commonCosts - $boughtCogs - $boughtFreight, 2);

            // A share of gross profit for gencys partners, taken on whichever
            // basis it sits beside. Only a positive gross owes anything — a
            // loss doesn't earn a rebate.
            $advisory = fn (float $gross) => ($gencysPartner && $gross > 0)
                ? round($gross * $advisoryRate, 2)
                : 0.0;

            return [
                'user_id' => $userId,
                'user_name' => $userId !== null ? ($names[$userId] ?? 'Unknown') : 'Unassigned',
                'delivered_orders' => $t->deliveredOrders,
                'delivered_units' => $t->deliveredUnits,
                'delivered_amount' => $revenue,
                'shipped_orders' => $t->shippedOrders,
                'total_shipping_fee' => $shippingFee,
                'ad_spent' => $adSpent,
                'cod_fee' => $codFee,
                'cod_fee_vat' => $codVat,
                'total_bought_cogs' => $boughtCogs,
                'total_bought_cogs_delivery_fee' => $boughtFreight,
                'total_delivered_cogs' => $deliveredCogs,
                'gross_profit_delivered_cogs' => $grossDelivered,
                'gross_profit_delivered_cogs_advisory_share' => $advisory($grossDelivered),
                'gross_profit_delivered_cogs_after_advisory_share' => round($grossDelivered - $advisory($grossDelivered), 2),
                'gross_profit_bought_cogs' => $grossBought,
                'gross_profit_bought_cogs_advisory_share' => $advisory($grossBought),
                'gross_profit_bought_cogs_after_advisory_share' => round($grossBought - $advisory($grossBought), 2),
            ];
        })->values();

        // The advisory and OPEX are allocated from the parent statement once
        // every row's gross profit is settled — see ClosesOutSlices.
        $rows = $this->closeSlice($statement, $rows->all());

        // Then the deficit carried into the month, entered at the seller-and-
        // product grain and added up to whatever grain this slice reports at.
        $rows = collect($this->applyCarriedLoss(
            $rows,
            $carried,
            fn (array $row) => (string) ($row['user_id'] ?? ''),
        ));

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

        // The company's OPEX split by type, which each row's own share is
        // then divided along.
        $opexTypes = $this->opexTypes($statement);

        // Which carried figures were typed rather than worked out — the page
        // marks those boxes, and clearing one has to mean something different
        // from clearing a box that only ever showed a derived figure.
        [$from] = $this->range($statement);
        $entered = $this->carryovers->entered($statement->workspace, $from);

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
            'gross_profit_delivered_cogs_advisory_share' => (float) $r->gross_profit_delivered_cogs_advisory_share,
            'gross_profit_delivered_cogs_after_advisory_share' => (float) $r->gross_profit_delivered_cogs_after_advisory_share,
            'gross_profit_bought_cogs' => (float) $r->gross_profit_bought_cogs,
            'gross_profit_bought_cogs_advisory_share' => (float) $r->gross_profit_bought_cogs_advisory_share,
            'gross_profit_bought_cogs_after_advisory_share' => (float) $r->gross_profit_bought_cogs_after_advisory_share,
            'opex' => (float) $r->opex,
            'opex_share_percentage' => (float) $r->opex_share_percentage,
            'opex_breakdown' => $this->opexByType($opexTypes, (float) $r->opex),
            'loss_brought_forward' => (float) $r->loss_brought_forward,
            'loss_brought_forward_entered' => array_key_exists((string) ($r->user_id ?? ''), $entered),
            'cumulative_profit_delivered_cogs' => (float) $r->cumulative_profit_delivered_cogs,
            'cumulative_profit_bought_cogs' => (float) $r->cumulative_profit_bought_cogs,
            'net_profit_delivered_cogs' => (float) $r->net_profit_delivered_cogs,
            'net_profit_bought_cogs' => (float) $r->net_profit_bought_cogs,
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
            'gross_profit_delivered_cogs_advisory_share' => $sum('gross_profit_delivered_cogs_advisory_share'),
            'gross_profit_delivered_cogs_after_advisory_share' => $sum('gross_profit_delivered_cogs_after_advisory_share'),
            'gross_profit_bought_cogs' => $sum('gross_profit_bought_cogs'),
            'gross_profit_bought_cogs_advisory_share' => $sum('gross_profit_bought_cogs_advisory_share'),
            'gross_profit_bought_cogs_after_advisory_share' => $sum('gross_profit_bought_cogs_after_advisory_share'),
            'opex' => $sum('opex'),
            'opex_breakdown' => $this->opexByType($opexTypes, $sum('opex')),
            'loss_brought_forward' => $sum('loss_brought_forward'),
            'loss_brought_forward_entered' => false,
            'cumulative_profit_delivered_cogs' => $sum('cumulative_profit_delivered_cogs'),
            'cumulative_profit_bought_cogs' => $sum('cumulative_profit_bought_cogs'),
            'opex_share_percentage' => $sum('opex_share_percentage'),
            'net_profit_delivered_cogs' => $sum('net_profit_delivered_cogs'),
            'net_profit_bought_cogs' => $sum('net_profit_bought_cogs'),
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
                'advisory' => (float) $statement->advisory_rate,
            ],
            'gencysPartner' => (bool) $statement->workspace->is_gencys_partner,
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

        return $this->sources->for($workspace)->unassignedUserDetail($workspace, $from, $to);
    }

    private function ensureSnapshot(IncomeStatement $statement): void
    {
        if (! $statement->userStatements()->exists()) {
            $this->snapshot($statement);
        }
    }

    /** @return array{0:Carbon, 1:Carbon} [from, to] for the statement's month. */
    private function range(IncomeStatement $statement): array
    {
        $start = $statement->period_month->copy()->startOfMonth();

        return [$start, $start->copy()->endOfMonth()];
    }
}
