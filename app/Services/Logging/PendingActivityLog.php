<?php

namespace App\Services\Logging;

use App\Enums\Logging\LogCategory;
use App\Enums\Logging\LogStatus;
use App\Enums\Logging\LogType;
use App\Enums\Logging\TriggerType;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fluent builder for a single {@see ActivityLog} entry. Accumulates attributes,
 * fills in request/auth context at persist time, and never lets a logging
 * failure bubble up into the caller.
 *
 * Obtain one via {@see ActivityLogger::build()}; call {@see save()} to persist.
 */
class PendingActivityLog
{
    /** @var array<string, mixed> */
    protected array $attributes = [
        'log_type' => LogType::User,
        'status' => LogStatus::Info,
    ];

    /** @var array<string, mixed> */
    protected array $metadata = [];

    public function asUser(): static
    {
        $this->attributes['log_type'] = LogType::User;

        return $this;
    }

    public function asSystem(): static
    {
        $this->attributes['log_type'] = LogType::System;

        return $this;
    }

    public function category(LogCategory $category): static
    {
        $this->attributes['category'] = $category;

        return $this;
    }

    public function action(string $actionType): static
    {
        $this->attributes['action_type'] = $actionType;

        return $this;
    }

    public function status(LogStatus $status): static
    {
        $this->attributes['status'] = $status;

        return $this;
    }

    public function message(?string $message): static
    {
        $this->attributes['message'] = $message;

        return $this;
    }

    public function user(int|User|null $user): static
    {
        $this->attributes['user_id'] = $user instanceof User ? $user->id : $user;

        return $this;
    }

    public function workspace(int|Workspace|null $workspace): static
    {
        $this->attributes['workspace_id'] = $workspace instanceof Workspace ? $workspace->id : $workspace;

        return $this;
    }

    /** Merge structured context. Repeated calls accumulate. */
    public function metadata(array $metadata): static
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    // ── System-log specifics ─────────────────────────────────────────────

    public function trigger(TriggerType $trigger): static
    {
        $this->attributes['trigger_type'] = $trigger;

        return $this;
    }

    public function schedule(?string $cron): static
    {
        $this->attributes['schedule'] = $cron;

        return $this;
    }

    public function job(?string $jobName): static
    {
        $this->attributes['job_name'] = $jobName;

        return $this;
    }

    /** Record a failure from a string or an exception (captures the trace). */
    public function error(Throwable|string|null $error): static
    {
        $this->attributes['status'] = LogStatus::Failure;

        if ($error instanceof Throwable) {
            $this->attributes['error_detail'] = (string) $error;
            $this->attributes['message'] ??= $error->getMessage();
            $this->metadata['exception'] = $error::class;
        } elseif ($error !== null) {
            $this->attributes['error_detail'] = $error;
        }

        return $this;
    }

    /**
     * Persist the entry. Returns the saved model, or null if logging itself
     * failed (the error is forwarded to the framework log so the audit gap is
     * visible without crashing the request/job).
     */
    public function save(): ?ActivityLog
    {
        try {
            return ActivityLog::create($this->resolveAttributes());
        } catch (Throwable $e) {
            Log::error('Failed to write activity log', [
                'reason' => $e->getMessage(),
                'attributes' => $this->attributes,
            ]);

            return null;
        }
    }

    /** @return array<string, mixed> */
    protected function resolveAttributes(): array
    {
        $attributes = $this->attributes;

        // Default the actor to the authenticated user when not set explicitly.
        $attributes['user_id'] ??= Auth::id();

        // Capture request context for user activity (no-op in console).
        if (($attributes['log_type'] ?? null) === LogType::User && app()->bound('request')) {
            $request = request();
            $attributes['ip_address'] ??= $request->ip();
            $attributes['user_agent'] ??= substr((string) $request->userAgent(), 0, 255);

            // Best-effort tenant resolution from the route's {workspace} binding.
            if (! isset($attributes['workspace_id'])) {
                $routeWorkspace = $request->route('workspace');
                if ($routeWorkspace instanceof Workspace) {
                    $attributes['workspace_id'] = $routeWorkspace->id;
                }
            }
        }

        $attributes['metadata'] = $this->metadata ?: null;

        return $attributes;
    }
}
