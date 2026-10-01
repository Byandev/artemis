<?php

namespace Modules\MetaAds\Jobs\Concerns;

use Illuminate\Support\Facades\Log;
use Modules\MetaAds\Exceptions\MetaGraphException;
use Modules\MetaAds\Models\AdAccount;
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
        $attempts = max(1, $this->attempts());
        $context = $this->buildErrorContext($run->id, $e, $attempts);

        if ($e instanceof MetaGraphException && $e->isRateLimited()) {
            $backoff = $e->resetSeconds
                ?? min(1800, $rateLimitBackoff * (2 ** ($attempts - 1)));

            Log::warning('Meta sync job rate limited, will retry', array_merge($context, [
                'backoff_seconds' => $backoff,
                'reset_hint' => $e->resetSeconds,
            ]));

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
            $backoff = min(300, $transientBackoff * (2 ** ($attempts - 1)));

            Log::warning('Meta sync job transient error, will retry', array_merge($context, [
                'backoff_seconds' => $backoff,
            ]));

            $run->markRateLimited($e, $backoff, [
                'error_code' => $e->errorCode,
                'error_subcode' => $e->errorSubcode,
                'transient' => true,
                'attempt' => $attempts,
            ]);

            $this->release($backoff);

            return;
        }

        Log::error('Meta sync job failed with unrecoverable error', $context);

        $run->fail($e);

        throw $e;
    }

    /**
     * Called by Laravel when the job has exhausted all retry attempts.
     * Ensures the SyncRun is marked failed and logs full diagnostic context.
     */
    public function failed(Throwable $e): void
    {
        $context = $this->buildErrorContext($this->syncRunId ?? null, $e, $this->attempts());
        $context['permanently_failed'] = true;

        Log::error('Meta sync job permanently failed after exhausting all retries', $context);

        if (! empty($this->syncRunId)) {
            SyncRun::find($this->syncRunId)?->fail($e);
        }
    }

    protected function resolveLastSuccessAt(string $entityType): ?int
    {
        $lastRun = SyncRun::where('entity_type', $entityType)
            ->where('scope_type', AdAccount::class)
            ->where('scope_id', $this->adAccount->id)
            ->where('status', SyncRun::STATUS_SUCCESS)
            ->latest('finished_at')
            ->first();

        return $lastRun?->finished_at?->timestamp;
    }

    private function buildErrorContext(?int $syncRunId, Throwable $e, int $attempts): array
    {
        $context = [
            'exception' => $e,          // Monolog formats this with the full stack trace
            'job' => static::class,
            'sync_run_id' => $syncRunId,
            'ad_account_id' => isset($this->adAccount) ? $this->adAccount?->id : null,
            'attempt' => $attempts,
            'max_tries' => $this->tries,
        ];

        if ($e instanceof MetaGraphException) {
            $context['error_code'] = $e->errorCode;
            $context['error_subcode'] = $e->errorSubcode;
            $context['error_type'] = $e->errorType;
            $context['fbtrace_id'] = $e->fbtraceId;
            $context['http_status'] = $e->httpStatus;
            $context['reset_seconds'] = $e->resetSeconds;
        }

        if (isset($this->afterCursor)) {
            $context['after_cursor'] = $this->afterCursor;
        }

        if (isset($this->runningCount)) {
            $context['running_count'] = $this->runningCount;
        }

        if (isset($this->date)) {
            $context['date'] = $this->date;
        }

        return $context;
    }
}
