<?php

namespace Modules\Finance\Statements\Contracts;

use App\Models\Workspace;
use Carbon\Carbon;
use Modules\Finance\Statements\OrderTotals;

/** Reads a month's orders for the workspace as a whole. */
interface ProvidesWorkspaceTotals
{
    public function workspaceTotals(Workspace $workspace, Carbon $from, Carbon $to): OrderTotals;
}
