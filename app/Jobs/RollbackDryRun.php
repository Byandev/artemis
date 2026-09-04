<?php

namespace App\Jobs;

use RuntimeException;

/**
 * Unwinds a dry run's transaction while carrying its counts back out.
 *
 * A dry run has to actually apply the updates to know how many rows each
 * statement would claim — they run in sequence and each depends on the last.
 * Throwing is how the transaction is discarded; the counts ride along so the
 * caller still gets its answer.
 */
class RollbackDryRun extends RuntimeException
{
    /**
     * @param  array<string, int>  $counts
     */
    public function __construct(public array $counts)
    {
        parent::__construct('Dry run rolled back.');
    }
}
