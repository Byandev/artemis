<?php

namespace Modules\Finance\Statements\Contracts;

/**
 * One system's orders, readable at each of the three grains a statement needs.
 *
 * Implementations know a schema and nothing else — no rates, no transactions,
 * no profit. Consumers depend on the narrow interface they actually use rather
 * than on this one, which exists so a source can be resolved and passed around
 * as a single thing.
 */
interface StatementOrderSource extends ProvidesProductTotals, ProvidesUserTotals, ProvidesWorkspaceTotals
{
    /** For messages and empty states: "gencys orders", "pancake orders". */
    public function label(): string;
}
