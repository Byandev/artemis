<?php

namespace Modules\MetaAds\Jobs\Concerns;

use Modules\MetaAds\Exceptions\MetaGraphException;
use Modules\MetaAds\Models\SyncRun;
use Throwable;

trait HandlesMetaSyncErrors
{
    /**
     * Decide what to do with a sync exception:
     *  - Rate-limit (Meta codes 4/17/32/613) → release with $rateLimitBackoff (default 5 min)
     *  - Transient   (Meta codes 1/2)        → release with $transientBackoff   (default 60 s)
     *  - Anything else                       → mark run as failed and rethrow
     */
    protected function handleSyncError(
        SyncRun $run,
        Throwable $e,
        int $rateLimitBackoff = 300,
        int $transientBackoff = 60,
    ): void {
        if ($e instanceof MetaGraphException && $e->isRateLimited()) {
            $run->markRateLimited($e, $rateLimitBackoff, [
                'error_code' => $e->errorCode,
                'error_subcode' => $e->errorSubcode,
            ]);

            $this->release($rateLimitBackoff);

            return;
        }

        if ($e instanceof MetaGraphException && $e->isTransient()) {
            $run->markRateLimited($e, $transientBackoff, [
                'error_code' => $e->errorCode,
                'error_subcode' => $e->errorSubcode,
                'transient' => true,
            ]);

            $this->release($transientBackoff);

            return;
        }

        $run->fail($e);

        throw $e;
    }
}
