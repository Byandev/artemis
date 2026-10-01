<?php

namespace Modules\Finance\Statements\Contracts;

use App\Models\Workspace;
use Carbon\Carbon;
use Modules\Finance\Statements\OrderTotals;
use Modules\Finance\Statements\UserProductKey;

/**
 * Reads a month's orders grouped by both the user they are credited to and the
 * product they carry — the grain the other two read one axis of.
 *
 * Summed along either axis this must agree with {@see ProvidesUserTotals} and
 * {@see ProvidesProductTotals} respectively; it is the same orders cut finer,
 * not a different reading of them.
 */
interface ProvidesUserProductTotals
{
    /**
     * Keyed by {@see UserProductKey::of()} — "userId|productId", with either
     * side empty when it resolves to nobody / no product.
     *
     * @return array<string, OrderTotals>
     */
    public function totalsByUserProduct(Workspace $workspace, Carbon $from, Carbon $to): array;
}
