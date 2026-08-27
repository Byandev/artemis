<?php

namespace Modules\Finance\Statements;

use App\Models\Workspace;
use Modules\Finance\Statements\Contracts\StatementOrderSource;
use Modules\Finance\Statements\Sources\GencysOrderSource;
use Modules\Finance\Statements\Sources\PancakeOrderSource;

/**
 * Picks the order source a workspace's statements read from.
 *
 * This is the one place that knows the rule, so adding a third system means
 * adding a source and a line here rather than editing every statement service.
 */
final class StatementOrderSourceFactory
{
    public function __construct(
        private readonly GencysOrderSource $gencys,
        private readonly PancakeOrderSource $pancake,
    ) {}

    public function for(Workspace $workspace): StatementOrderSource
    {
        return $workspace->is_gencys_partner ? $this->gencys : $this->pancake;
    }
}
