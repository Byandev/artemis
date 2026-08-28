<?php

namespace Modules\Finance\Statements\Contracts;

use App\Models\Workspace;
use Carbon\Carbon;
use Modules\Finance\Statements\OrderTotals;

/** Reads a month's orders grouped by the user they are credited to. */
interface ProvidesUserTotals
{
    /**
     * Keyed by user id as a string; '' collects orders credited to nobody.
     *
     * @return array<string, OrderTotals>
     */
    public function totalsByUser(Workspace $workspace, Carbon $from, Carbon $to): array;

    /**
     * What is sitting in the unassigned bucket. `label` is whatever the source
     * failed to match on — an intern name, a page, and so on.
     *
     * @return list<array{label:?string, delivered_orders:int, delivered_amount:float, shipped_orders:int, shipping_fee:float}>
     */
    public function unassignedUserDetail(Workspace $workspace, Carbon $from, Carbon $to): array;
}
