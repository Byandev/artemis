<?php

namespace App\Support\Logging;

use App\Enums\Logging\LogCategory;
use App\Enums\Logging\LogStatus;
use App\Enums\Logging\TriggerType;
use App\Services\Logging\ActivityLogger;
use Closure;
use Throwable;

/**
 * Drop-in for queued jobs and console commands. Wrap the body of `handle()` in
 * {@see withActivityLog()} to emit a system log on success and a failure log
 * (with the exception) when it throws — while preserving the original failure
 * so the queue's retry/`failed()` machinery still kicks in.
 *
 * Example (inside a job):
 *   return $this->withActivityLog('fetch-erp-inventory', fn () => $this->fetch(),
 *       schedule: '0 9,12,17 * * *');
 */
trait LogsJobActivity
{
    /**
     * @template T
     *
     * @param  Closure():T  $work
     * @param  array<string, mixed>  $metadata
     * @return T
     */
    protected function withActivityLog(
        string $action,
        Closure $work,
        LogCategory $category = LogCategory::ScheduledJob,
        TriggerType $trigger = TriggerType::Scheduled,
        ?string $schedule = null,
        array $metadata = [],
    ): mixed {
        $logger = app(ActivityLogger::class);
        $startedAt = microtime(true);

        try {
            $result = $work();

            $logger->systemEvent($category, $action, [
                'status' => LogStatus::Success,
                'trigger' => $trigger,
                'schedule' => $schedule,
                'job_name' => static::class,
                'message' => sprintf('%s completed', $action),
                'metadata' => $metadata + ['duration_ms' => $this->elapsedMs($startedAt)],
            ]);

            return $result;
        } catch (Throwable $e) {
            $logger->systemEvent($category, $action, [
                'status' => LogStatus::Failure,
                'trigger' => $trigger,
                'schedule' => $schedule,
                'job_name' => static::class,
                'message' => sprintf('%s failed', $action),
                'error' => $e,
                'metadata' => $metadata + ['duration_ms' => $this->elapsedMs($startedAt)],
            ]);

            // Re-throw so normal job failure handling / retries are unaffected.
            throw $e;
        }
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
