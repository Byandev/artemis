<?php

namespace App\Services\Logging;

use App\Enums\Logging\LogCategory;
use App\Enums\Logging\LogStatus;
use App\Facades\Activity;
use App\Models\ActivityLog;
use Throwable;

/**
 * Single entry point for writing activity / system logs. Resolve from the
 * container (`app(ActivityLogger::class)`) or use the {@see Activity}
 * facade.
 *
 * Two layers of API:
 *   - high-level helpers ({@see userActivity()}, {@see systemEvent()},
 *     {@see failure()}) for the common cases;
 *   - {@see build()} for full fluent control.
 */
class ActivityLogger
{
    /** Start a fluent builder for full control over the entry. */
    public function build(): PendingActivityLog
    {
        return new PendingActivityLog;
    }

    /**
     * Record a user-initiated action (the audit trail).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function userActivity(
        LogCategory $category,
        string $action,
        LogStatus $status = LogStatus::Success,
        ?string $message = null,
        array $metadata = [],
    ): ?ActivityLog {
        return $this->build()
            ->asUser()
            ->category($category)
            ->action($action)
            ->status($status)
            ->message($message)
            ->metadata($metadata)
            ->save();
    }

    /**
     * Record an automated system event (scheduled job, integration, etc.).
     *
     * Supported $options keys: trigger (TriggerType), schedule (string cron),
     * job_name (string), message (string), metadata (array), status (LogStatus),
     * workspace_id (int), error (Throwable|string).
     *
     * @param  array<string, mixed>  $options
     */
    public function systemEvent(
        LogCategory $category,
        string $action,
        array $options = [],
    ): ?ActivityLog {
        $pending = $this->build()
            ->asSystem()
            ->category($category)
            ->action($action)
            ->status($options['status'] ?? LogStatus::Success)
            ->message($options['message'] ?? null)
            ->metadata($options['metadata'] ?? []);

        if (isset($options['trigger'])) {
            $pending->trigger($options['trigger']);
        }
        if (isset($options['schedule'])) {
            $pending->schedule($options['schedule']);
        }
        if (isset($options['job_name'])) {
            $pending->job($options['job_name']);
        }
        if (isset($options['workspace_id'])) {
            $pending->workspace($options['workspace_id']);
        }
        if (isset($options['error'])) {
            $pending->error($options['error']);
        }

        return $pending->save();
    }

    /**
     * Shorthand for a failed action. Accepts an exception (captured with its
     * trace) or a plain message.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function failure(
        LogCategory $category,
        string $action,
        Throwable|string|null $error = null,
        ?string $message = null,
        array $metadata = [],
    ): ?ActivityLog {
        return $this->build()
            ->category($category)
            ->action($action)
            ->message($message)
            ->metadata($metadata)
            ->error($error)
            ->save();
    }
}
