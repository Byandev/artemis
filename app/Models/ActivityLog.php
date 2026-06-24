<?php

namespace App\Models;

use App\Enums\Logging\LogCategory;
use App\Enums\Logging\LogStatus;
use App\Enums\Logging\LogType;
use App\Enums\Logging\TriggerType;
use App\Services\Logging\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single immutable audit / observability record. Write via the
 * {@see ActivityLogger} service rather than touching
 * this model directly so the schema stays consistent.
 *
 * @property LogType $log_type
 * @property LogCategory $category
 * @property LogStatus $status
 * @property TriggerType|null $trigger_type
 */
class ActivityLog extends Model
{
    /** Logs are append-only; there is no updated_at. */
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'log_type' => LogType::class,
        'category' => LogCategory::class,
        'status' => LogStatus::class,
        'trigger_type' => TriggerType::class,
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    // ── Query scopes ─────────────────────────────────────────────────────

    public function scopeFailures(Builder $query): Builder
    {
        return $query->where('status', LogStatus::Failure->value);
    }

    public function scopeStatus(Builder $query, LogStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function scopeOfType(Builder $query, LogType $type): Builder
    {
        return $query->where('log_type', $type->value);
    }

    public function scopeCategory(Builder $query, LogCategory $category): Builder
    {
        return $query->where('category', $category->value);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForWorkspace(Builder $query, int $workspaceId): Builder
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeForJob(Builder $query, string $jobName): Builder
    {
        return $query->where('job_name', $jobName);
    }

    /** Entries created within the last N hours. */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    public function scopeBetween(Builder $query, mixed $from, mixed $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }
}
