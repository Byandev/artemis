<?php

namespace Modules\Finance\Statements;

use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\TransactionType;

/**
 * Money out of the finance ledger, read the three ways a statement needs it:
 * for the workspace, per product tag, and per person charged.
 *
 * Transaction types are still found by matching their name, which is fragile —
 * rename a type and its column silently empties. It belongs on a configured
 * field like `income_statement_section` already is. Kept in one place here so
 * that change lands once rather than in every statement service.
 */
final class TransactionTotals
{
    public const AD_SPENT = ['%adspent%', '%ad spent%', '%ad spend%'];

    public const COST_OF_GOODS = ['%cost of goods%', '%cogs%'];

    /**
     * Freight on a goods purchase. The wording varies — "Delivery of COG",
     * "Delivery Fee of COGS", "COG Delivery" — so the word order is matched
     * loosely rather than as fixed phrases.
     */
    public const COG_DELIVERY = [
        '%delivery%cog%',
        '%cog%delivery%',
        '%delivery%goods%',
        '%goods%delivery%',
    ];

    /**
     * The month's operating expenses: every outflow whose transaction type is
     * marked OPEX on the income statement.
     *
     * Unlike the lookups below this is driven by configuration rather than by
     * matching a type's name, so renaming a type can't quietly empty it.
     *
     * Outflow with no type at all is left out — it isn't marked as anything, and
     * guessing it into OPEX would overstate expenses.
     */
    public function opexForWorkspace(Workspace $workspace, Carbon $from, Carbon $to): float
    {
        $typeIds = TransactionType::where('workspace_id', $workspace->id)
            ->where('income_statement_section', 'opex')
            ->pluck('id');

        if ($typeIds->isEmpty()) {
            return 0.0;
        }

        return round((float) $this->outflow($workspace, $from, $to, $typeIds->all())->sum('amount'), 2);
    }

    /**
     * The same OPEX, split by transaction type — the rows sum to
     * {@see opexForWorkspace()}. Keyed by transaction type id, biggest first,
     * with types that had no outflow left out.
     *
     * @return array<int, float>
     */
    public function opexByTypeForWorkspace(Workspace $workspace, Carbon $from, Carbon $to): array
    {
        $typeIds = TransactionType::where('workspace_id', $workspace->id)
            ->where('income_statement_section', 'opex')
            ->pluck('id');

        if ($typeIds->isEmpty()) {
            return [];
        }

        return $this->outflow($workspace, $from, $to, $typeIds->all())
            ->groupBy('transaction_type_id')
            ->selectRaw('transaction_type_id, SUM(amount) as amount')
            ->orderByDesc('amount')
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->transaction_type_id => round((float) $r->amount, 2)])
            ->all();
    }

    /** The month's whole outflow for the matching types. */
    public function forWorkspace(Workspace $workspace, Carbon $from, Carbon $to, array $patterns): float
    {
        $typeIds = $this->typeIds($workspace, $patterns);

        if (empty($typeIds)) {
            return 0.0;
        }

        return round((float) $this->outflow($workspace, $from, $to, $typeIds)->sum('amount'), 2);
    }

    /**
     * Product-tagged shares, keyed by product id as a string ('' = a tag that
     * matches no product).
     *
     * `$withUntagged` adds the rest of the month's outflow on these types —
     * what no product share accounts for — to that same '' bucket. Costs that
     * have to reconcile to the workspace figure want it on: a goods purchase
     * nobody tagged is still a goods purchase, and dropping it would leave
     * every slice quietly short of the statement they belong to. It stays
     * unattributed rather than being spread over products on a guess.
     *
     * Off by default, and deliberately left off for ad spend, where only what
     * someone attributed is counted (see the product statement's own note).
     *
     * @return array<string, float>
     */
    public function byProductTag(Workspace $workspace, Carbon $from, Carbon $to, array $patterns, bool $withUntagged = false): array
    {
        $typeIds = $this->typeIds($workspace, $patterns);

        if (empty($typeIds)) {
            return [];
        }

        $rows = DB::table('finance_transaction_products as tp')
            ->join('finance_transactions as t', 't.id', '=', 'tp.transaction_id')
            ->where('t.workspace_id', $workspace->id)
            ->where('t.type', 'out')
            ->whereIn('t.transaction_type_id', $typeIds)
            ->whereBetween('t.date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('tp.product')
            ->selectRaw('tp.product as name, SUM(tp.amount) as amount')
            ->get();

        $totals = [];

        if ($rows->isNotEmpty()) {
            $idByName = DB::table('products')
                ->where('workspace_id', $workspace->id)
                ->whereIn('name', $rows->pluck('name')->unique()->all())
                ->pluck('id', 'name');

            foreach ($rows as $r) {
                $key = isset($idByName[$r->name]) ? (string) $idByName[$r->name] : '';
                $totals[$key] = round(($totals[$key] ?? 0) + (float) $r->amount, 2);
            }
        }

        if ($withUntagged) {
            $outflow = round((float) $this->outflow($workspace, $from, $to, $typeIds)->sum('amount'), 2);
            $untagged = round($outflow - round(array_sum($totals), 2), 2);

            if (abs($untagged) >= 0.01) {
                $totals[''] = round(($totals[''] ?? 0) + $untagged, 2);
            }
        }

        return $totals;
    }

    /**
     * Charge-to shares, keyed by user id as a string. A transaction split
     * across several people contributes each person's share.
     *
     * @return array<string, float>
     */
    public function byChargedUser(Workspace $workspace, Carbon $from, Carbon $to, array $patterns): array
    {
        $typeIds = $this->typeIds($workspace, $patterns);

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
            ->mapWithKeys(fn ($r) => [(string) (int) $r->user_id => round((float) $r->amount, 2)])
            ->all();
    }

    private function outflow(Workspace $workspace, Carbon $from, Carbon $to, array $typeIds)
    {
        return DB::table('finance_transactions')
            ->where('workspace_id', $workspace->id)
            ->where('type', 'out')
            ->whereIn('transaction_type_id', $typeIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * The types a set of patterns resolves to.
     *
     * Freight is held out of every other lookup: a name like "Delivery Fee of
     * COGS" reads as both the goods and the freight on them, and it is the
     * freight — counting it on both lines would charge the purchase twice.
     *
     * @param  list<string>  $patterns
     * @return list<int>
     */
    private function typeIds(Workspace $workspace, array $patterns): array
    {
        $ids = $this->matching($workspace, $patterns);

        if ($patterns !== self::COG_DELIVERY) {
            $ids = array_values(array_diff($ids, $this->matching($workspace, self::COG_DELIVERY)));
        }

        return $ids;
    }

    /**
     * @param  list<string>  $patterns
     * @return list<int>
     */
    private function matching(Workspace $workspace, array $patterns): array
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
}
