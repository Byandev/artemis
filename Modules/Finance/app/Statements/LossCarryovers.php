<?php

namespace Modules\Finance\Statements;

use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Finance\Models\LossCarryover;

/**
 * The deficits carried into a month, read at whichever grain is asking.
 *
 * They are entered against one seller and one product, and everything above
 * that is those entries added up — a person's across their products, a
 * product's across its sellers, the month's across both. Reading them through
 * one place is what keeps those three answers consistent with each other and
 * with the entries themselves.
 */
final class LossCarryovers
{
    /**
     * Every entry for the month, keyed by {@see UserProductKey}.
     *
     * @return array<string, float>
     */
    public function byUserProduct(Workspace $workspace, Carbon $month): array
    {
        return $this->entries($workspace, $month)
            ->mapWithKeys(fn (LossCarryover $c) => [
                UserProductKey::of($c->user_id, $c->product_id) => round((float) $c->amount, 2),
            ])
            ->all();
    }

    /**
     * Added up per seller, keyed by user id as a string ('' = carried by
     * nobody in particular).
     *
     * @return array<string, float>
     */
    public function byUser(Workspace $workspace, Carbon $month): array
    {
        return $this->foldBy($workspace, $month, fn (LossCarryover $c) => (string) ($c->user_id ?? ''));
    }

    /**
     * Added up per product, keyed by product id as a string ('' = carried
     * against no product).
     *
     * @return array<string, float>
     */
    public function byProduct(Workspace $workspace, Carbon $month): array
    {
        return $this->foldBy($workspace, $month, fn (LossCarryover $c) => (string) ($c->product_id ?? ''));
    }

    /** The month's whole carried deficit — every entry, added up. */
    public function forWorkspace(Workspace $workspace, Carbon $month): float
    {
        return round((float) $this->entries($workspace, $month)->sum('amount'), 2);
    }

    /** @return array<string, float> */
    private function foldBy(Workspace $workspace, Carbon $month, callable $key): array
    {
        $totals = [];

        foreach ($this->entries($workspace, $month) as $carryover) {
            $k = $key($carryover);
            $totals[$k] = round(($totals[$k] ?? 0) + (float) $carryover->amount, 2);
        }

        return $totals;
    }

    /** @return Collection<int, LossCarryover> */
    private function entries(Workspace $workspace, Carbon $month)
    {
        return LossCarryover::where('workspace_id', $workspace->id)
            ->whereDate('period_month', $month->copy()->startOfMonth())
            ->get();
    }
}
