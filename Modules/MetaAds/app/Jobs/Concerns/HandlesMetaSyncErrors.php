<?php

namespace Modules\MetaAds\Jobs\Concerns;

use Modules\MetaAds\Exceptions\MetaGraphException;
use Modules\MetaAds\Models\SyncRun;
use Throwable;

trait HandlesMetaSyncErrors
{
    /**
     * Decide what to do with a sync exception:
     *  - Rate-limit (Meta codes 4/17/32/613/80000+) → release with Meta's own
     *    estimated_time_to_regain_access if present, else exponential backoff
     *    (min $rateLimitBackoff, doubled per attempt, capped at 30 min)
     *  - Transient   (Meta codes 1/2)               → release with exponential
     *    backoff (min $transientBackoff, capped at 5 min)
     *  - Anything else                              → mark run as failed and rethrow
     */
    protected function handleSyncError(
        SyncRun $run,
        Throwable $e,
        int $rateLimitBackoff = 300,
        int $transientBackoff = 60,
    ): void {
        if ($e instanceof MetaGraphException && $e->isRateLimited()) {
            // Prefer Meta's own hint; otherwise exponential backoff with floor.
            $attempts = max(1, $this->attempts());
            $backoff = $e->resetSeconds
                ?? min(1800, $rateLimitBackoff * (2 ** ($attempts - 1)));

            $run->markRateLimited($e, $backoff, [
                'error_code' => $e->errorCode,
                'error_subcode' => $e->errorSubcode,
                'reset_hint' => $e->resetSeconds,
                'attempt' => $attempts,
            ]);

            $this->release($backoff);

            return;
        }

        if ($e instanceof MetaGraphException && $e->isTransient()) {
            $attempts = max(1, $this->attempts());
            $backoff = min(300, $transientBackoff * (2 ** ($attempts - 1)));

            $run->markRateLimited($e, $backoff, [
                'error_code' => $e->errorCode,
                'error_subcode' => $e->errorSubcode,
                'transient' => true,
                'attempt' => $attempts,
            ]);

            $this->release($backoff);

            return;
        }

        $run->fail($e);

        throw $e;
    }
}
