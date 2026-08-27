<?php

namespace Modules\Finance\Statements\Contracts;

use App\Models\Workspace;
use Carbon\Carbon;
use Modules\Finance\Statements\OrderTotals;

/** Reads a month's orders grouped by the product they carry. */
interface ProvidesProductTotals
{
    /**
     * Keyed by product id as a string; '' collects orders that resolve to no
     * product.
     *
     * @return array<string, OrderTotals>
     */
    public function totalsByProduct(Workspace $workspace, Carbon $from, Carbon $to): array;

    /**
     * What is sitting in the unresolved bucket, so it can be explained rather
     * than just reported. `label` is whatever the source failed to match on.
     *
     * @return list<array{label:?string, delivered_orders:int, delivered_amount:float, shipped_orders:int, shipping_fee:float}>
     */
    public function unresolvedProductDetail(Workspace $workspace, Carbon $from, Carbon $to): array;
}
