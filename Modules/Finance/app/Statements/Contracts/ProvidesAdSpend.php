<?php

namespace Modules\Finance\Statements\Contracts;

use App\Models\Workspace;
use Carbon\Carbon;
use Modules\Finance\Statements\UserProductKey;

/**
 * Where a workspace's advertising spend is recorded.
 *
 * It isn't the same place for everyone: a gencys workspace books it through the
 * finance ledger, while a pancake one has it counted per page per day by the
 * ads sync. Same figure, different origin, so it belongs with the order source
 * rather than in the statement services.
 */
interface ProvidesAdSpend
{
    public function workspaceAdSpend(Workspace $workspace, Carbon $from, Carbon $to): float;

    /**
     * Keyed by product id as a string; '' collects spend attributed to no
     * product.
     *
     * @return array<string, float>
     */
    public function adSpendByProduct(Workspace $workspace, Carbon $from, Carbon $to): array;

    /**
     * Keyed by user id as a string; '' collects spend attributed to nobody.
     *
     * @return array<string, float>
     */
    public function adSpendByUser(Workspace $workspace, Carbon $from, Carbon $to): array;

    /**
     * Spend tied to a user and a product at once, keyed by
     * {@see UserProductKey}.
     *
     * Null where the source cannot say: it knows the spend per product and per
     * person, but nothing that ties one figure to both, so a caller wanting
     * that grain has to apportion the per-product figure instead. Returning
     * null rather than an empty array keeps "cannot say" apart from "spent
     * nothing", which are not the same answer.
     *
     * @return array<string, float>|null
     */
    public function adSpendByUserProduct(Workspace $workspace, Carbon $from, Carbon $to): ?array;
}
