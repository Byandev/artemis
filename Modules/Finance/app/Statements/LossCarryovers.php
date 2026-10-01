<?php

namespace Modules\Finance\Statements;

use App\Models\Workspace;
use Carbon\Carbon;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Models\LossCarryover;

/**
 * What each person carried into a month, and the month's total.
 *
 * A deficit belongs to a person, not to one of their products. An advertiser's
 * products are netted against each other within the month, so a losing one has
 * already eaten into what the winners earned them; carrying that product's loss
 * separately would charge it a second time. Only the person's own closing
 * position carries, and only when it is negative.
 *
 * It is normally read rather than typed: last month's per-user statement
 * already says where each person finished, so this month follows from it. A
 * figure is only entered by hand for a month whose predecessor was closed
 * somewhere else — a spreadsheet, before any of this existed — and that entry
 * then wins for that month.
 */
final class LossCarryovers
{
    /**
     * The basis the carried figure is read from.
     *
     * One number is carried but a month has two net profits, one per
     * cost-of-goods basis, so one has to be chosen. Bought is the basis the
     * statements actually display, and the one a commission conversation is
     * had in.
     */
    private const BASIS = 'cumulative_profit_bought_cogs';

    /**
     * What each person carries into the month, keyed by user id as a string
     * ('' = credited to nobody).
     *
     * A hand-entered figure wins; otherwise it is last month's closing position
     * where that was negative. Someone who ended last month in profit carries
     * nothing, however many of their products lost money — those losses were
     * already taken out of the month they happened in.
     *
     * @return array<string, float>
     */
    public function forUsers(Workspace $workspace, Carbon $month): array
    {
        $carried = $this->derivedFromLastMonth($workspace, $month);

        foreach ($this->entered($workspace, $month) as $userKey => $amount) {
            $carried[$userKey] = $amount;
        }

        return array_filter($carried, fn (float $amount) => $amount > 0);
    }

    /** The month's whole carried deficit — every person's, added up. */
    public function forWorkspace(Workspace $workspace, Carbon $month): float
    {
        return round(array_sum($this->forUsers($workspace, $month)), 2);
    }

    /**
     * Only the figures typed in, so a page can tell what was stated from what
     * was worked out — the box shows one and not the other.
     *
     * @return array<string, float>
     */
    public function entered(Workspace $workspace, Carbon $month): array
    {
        return LossCarryover::where('workspace_id', $workspace->id)
            ->whereDate('period_month', $month->copy()->startOfMonth())
            ->get()
            ->mapWithKeys(fn (LossCarryover $c) => [
                (string) ($c->user_id ?? '') => round((float) $c->amount, 2),
            ])
            ->all();
    }

    /**
     * Where each person finished last month, as an amount to carry: the
     * negative of their closing position, or nothing if they ended up.
     *
     * @return array<string, float>
     */
    private function derivedFromLastMonth(Workspace $workspace, Carbon $month): array
    {
        $previous = IncomeStatement::where('workspace_id', $workspace->id)
            ->whereDate('period_month', $month->copy()->startOfMonth()->subMonthNoOverflow())
            ->first();

        if (! $previous) {
            return [];
        }

        return $previous->userStatements()
            ->get()
            ->mapWithKeys(function ($row) {
                $closed = (float) $row->{self::BASIS};

                return [(string) ($row->user_id ?? '') => $closed < 0 ? round(abs($closed), 2) : 0.0];
            })
            ->all();
    }
}
