<?php

namespace Modules\MetaAds\Jobs\Concerns;

use Modules\MetaAds\Exceptions\MetaGraphException;
use Modules\MetaAds\Models\SyncRun;
use Throwable;

trait HandlesMetaSyncErrors
{
    /**
     * Decide what to do with a sync exception. If Meta is throttling, release the job
     * back to the queue with a backoff and mark the run as rate-limited. Otherwise,
     * fail the run and rethrow so the queue worker records the failure normally.
     */
    protected function handleSyncError(SyncRun $run, Throwable $e, int $rateLimitBackoff = 300): void
    {
        if ($e instanceof MetaGraphException && $e->isRateLimited()) {
            $run->markRateLimited($e, $rateLimitBackoff, [
                'error_code' => $e->errorCode,
                'error_subcode' => $e->errorSubcode,
            ]);

            $this->release($rateLimitBackoff);

            return;
        }

        $run->fail($e);

        throw $e;
    }
}
