<?php

namespace Modules\Finance\Services\Concerns;

use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Statements\ProportionalSplit;

/**
 * The two lines every statement slice closes with — the advisory share and
 * OPEX — and the net profit they leave behind.
 *
 * Both are company-level figures: one charge on the month's profit, one pool of
 * running costs, neither booked against any particular user or product. So a
 * row's share of each is allocated from the parent statement's own figure
 * rather than worked out again per row.
 *
 * Allocating rather than recomputing is what lets the slices tally. Struck per
 * row, the advisory would not: the agreement charges the lower of two bases
 * (a share of gross profit, or a share of delivered revenue) and a loss-making
 * row owes nothing, so which basis wins and which rows are negative both change
 * with how the rows are grouped — and the same month would come to a different
 * total on the per-user page than on the per-product one. Taken from the parent
 * and split, every slice adds back to the statement it belongs to.
 *
 * Shared by all three slice services so they cannot drift apart.
 */
trait ClosesOutSlices
{
    /** The two cost-of-goods bases a statement carries side by side. */
    private const BASES = ['delivered_cogs', 'bought_cogs'];

    /**
     * Pull the per-row COD fee and its VAT onto the statement's own figures,
     * and re-settle gross profit from them.
     *
     * Each row's fee is worked out as a percentage of that row's revenue and
     * rounded to the centavo, so a month split eight ways and the same month
     * split thirty-four ways round differently and land a centavo or two apart.
     * Small, but a statement that does not add up invites the reader to distrust
     * the rest of it, so the rounding is nudged onto the parent's figure and the
     * rows still come out within a centavo of their own percentage.
     *
     * Only rounding is nudged. When the rows and the statement disagree by more
     * than that, the statement is stale — saved before a rate changed, or before
     * a column existed — and forcing good rows onto a bad total would turn a
     * visible mismatch into a hidden one, so the rows are left exactly as they
     * were computed.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function alignFees(IncomeStatement $statement, array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }

        foreach (['cod_fee', 'cod_fee_vat'] as $fee) {
            $weights = array_map(fn ($row) => max((float) $row[$fee], 0.0), $rows);
            $rowsTotal = array_sum($weights);
            $target = (float) $statement->$fee;

            if (! $this->isRoundingApart($rowsTotal, $target)) {
                continue;
            }

            foreach (ProportionalSplit::of($target, $weights) as $i => $share) {
                $rows[$i][$fee] = $share;
            }
        }

        // Gross profit is the subtraction those fees sit inside, so it is taken
        // again rather than adjusted — the two bases differ only in which cost
        // of goods they charge.
        foreach ($rows as $i => $row) {
            $common = (float) $row['ad_spent']
                + (float) $row['total_shipping_fee']
                + (float) $row['cod_fee']
                + (float) $row['cod_fee_vat'];

            $revenue = (float) $row['delivered_amount'];

            $rows[$i]['gross_profit_delivered_cogs'] = round(
                $revenue - $common - (float) $row['total_delivered_cogs'], 2);
            $rows[$i]['gross_profit_bought_cogs'] = round(
                $revenue - $common - (float) $row['total_bought_cogs'] - (float) $row['total_bought_cogs_delivery_fee'], 2);
        }

        return $rows;
    }

    /**
     * Whether two totals differ only by the rounding of the rows behind them —
     * a few centavos, or a hair of the total on a large one. A wider gap is a
     * real disagreement and must not be papered over.
     */
    private function isRoundingApart(float $rows, float $target): bool
    {
        return abs($rows - $target) <= max(0.05, abs($rows) * 0.000001);
    }

    /**
     * Share the statement's advisory charge across the rows, in proportion to
     * the gross profit each made.
     *
     * Only a positive gross carries any of it — a row that lost money owes
     * nothing, which is the same rule the workspace figure was struck under.
     * Each cost-of-goods basis is allocated from its own pool, since the two
     * have their own gross profit and their own charge.
     *
     * When no row is in profit the charge stays unallocated: there is nothing
     * for it to sit on, and spreading it over loss-makers would invent a
     * liability the agreement does not create.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function allocateAdvisory(IncomeStatement $statement, array $rows): array
    {
        foreach (self::BASES as $basis) {
            $gross = "gross_profit_{$basis}";
            $share = "{$gross}_advisory_share";
            $after = "{$gross}_after_advisory_share";

            $shares = ProportionalSplit::of(
                (float) $statement->$share,
                array_map(fn ($row) => max((float) $row[$gross], 0.0), $rows),
            );

            foreach ($rows as $i => $row) {
                $charged = round($shares[$i] ?? 0.0, 2);

                $rows[$i][$share] = $charged;
                $rows[$i][$after] = round((float) $row[$gross] - $charged, 2);
            }
        }

        return $rows;
    }

    /**
     * Share the month's OPEX across the rows and settle net profit.
     *
     * OPEX follows parcels, so a row takes the share matching its delivered
     * orders over every row's here. One that delivered nothing carries none of
     * it, and when nothing delivered at all the pool stays unallocated rather
     * than landing on rows that did not earn it.
     *
     * The percentage behind each share is saved beside the amount: the counts
     * it was struck from can move with a later sync, and a saved statement
     * should still be able to say what split it actually used.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function closeOut(IncomeStatement $statement, array $rows): array
    {
        $weights = array_map(fn ($row) => (int) $row['delivered_orders'], $rows);
        $delivered = array_sum($weights);

        $shares = ProportionalSplit::of((float) $statement->opex, $weights);

        foreach ($rows as $i => $row) {
            $opex = round($shares[$i] ?? 0.0, 2);

            $rows[$i]['opex'] = $opex;
            $rows[$i]['opex_share_percentage'] = $delivered > 0
                ? round($weights[$i] / $delivered * 100, 6)
                : 0.0;

            foreach (self::BASES as $basis) {
                $rows[$i]["net_profit_{$basis}"] = round(
                    (float) $rows[$i]["gross_profit_{$basis}_after_advisory_share"] - $opex,
                    2,
                );
            }
        }

        return $rows;
    }

    /**
     * Every closing step, in the order a statement reads them: settle the fees
     * and the gross profit they sit inside, then the advisory on that profit,
     * then OPEX and what it leaves.
     */
    private function closeSlice(IncomeStatement $statement, array $rows): array
    {
        return $this->closeOut(
            $statement,
            $this->allocateAdvisory($statement, $this->alignFees($statement, $rows)),
        );
    }
}
