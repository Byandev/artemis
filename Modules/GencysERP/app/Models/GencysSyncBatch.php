<?php

namespace Modules\GencysERP\Models;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One ERP sync job of work, holding the GencysSyncRun rows it walks through.
 *
 * A batch covers one or more sync types — a scheduled pass carries all of them,
 * a hand-raised one usually carries a single type — and works through them in the
 * order they're listed. Every run records its own type, so a group sent to n8n
 * never mixes them.
 *
 * A batch is created `queued` with every run it will ever send. Only one batch
 * runs at a time (a batch spans all ERP-connected workspaces, and a workspace has
 * exactly one ERP login), so a batch raised while another is in flight simply
 * waits its turn — nothing is ever cancelled to make room. The runs inside go out
 * to n8n in groups, and a group is only sent once the previous one has come back
 * or timed out. See BatchRunner for the walk itself.
 */
class GencysSyncBatch extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_COMPLETED_WITH_FAILURES = 'completed_with_failures';

    public const STATUS_CANCELLED = 'cancelled';

    /** Statuses that still hold (or are waiting to hold) the ERP. */
    public const ACTIVE_STATUSES = [self::STATUS_QUEUED, self::STATUS_RUNNING];

    public const SOURCE_CRON = 'cron';

    public const SOURCE_MANUAL = 'manual';

    protected $table = 'gencys_sync_batches';

    protected $guarded = [];

    protected $casts = [
        'sync_types' => 'array',
        'parameters' => 'array',
        'group_size' => 'integer',
        'timeout_seconds' => 'integer',
        'max_retries' => 'integer',
        'total_runs' => 'integer',
        'succeeded_runs' => 'integer',
        'failed_runs' => 'integer',
        'cancelled_runs' => 'integer',
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(GencysSyncRun::class, 'gencys_sync_batch_id');
    }

    /**
     * Open a queued batch, or hand back an equivalent one that hasn't started yet.
     *
     * The schedule fires each sync type several times a day and nothing is ever
     * cancelled to make room, so without this a slow morning would leave three
     * identical transaction batches stacked up, each re-fetching what the one
     * before it already did. Two batches are "equivalent" when they're the same
     * sync type raised with the same options.
     */
    public static function open(
        array $syncTypes,
        array $parameters = [],
        ?int $groupSize = null,
        ?int $workspaceId = null,
        string $source = self::SOURCE_CRON,
        ?int $createdByUserId = null,
        ?int $timeoutSeconds = null,
        ?int $maxRetries = null,
    ): self {
        $signature = self::signatureFor($syncTypes, $parameters, $workspaceId);

        $existing = self::query()
            ->where('status', self::STATUS_QUEUED)
            ->where('parameters_signature', $signature)
            ->first();

        if ($existing) {
            return $existing;
        }

        return self::create([
            'workspace_id' => $workspaceId,
            'created_by_user_id' => $createdByUserId,
            'sync_types' => array_values($syncTypes),
            'status' => self::STATUS_QUEUED,
            'source' => $source,
            'parameters' => $parameters ?: null,
            'parameters_signature' => $signature,
            'group_size' => $groupSize ? max(1, $groupSize) : null,
            'timeout_seconds' => $timeoutSeconds ?? config('gencyserp.batch.timeout_seconds', 600),
            'max_retries' => $maxRetries ?? config('gencyserp.batch.max_retries', 2),
            // Written explicitly rather than left to the column defaults so the
            // returned model reports 0 instead of null before refreshCounts().
            'total_runs' => 0,
            'succeeded_runs' => 0,
            'failed_runs' => 0,
            'cancelled_runs' => 0,
            'queued_at' => now(),
        ]);
    }

    /** Stable identity for "the same sync types, asked the same way". */
    public static function signatureFor(array $syncTypes, array $parameters, ?int $workspaceId = null): string
    {
        // Order matters — it's the order the batch works through them — so the
        // list is hashed as given rather than sorted.
        return hash('sha256', implode('|', [
            implode(',', array_values($syncTypes)),
            $workspaceId ?? 'all',
            GencysSyncRun::parameterSignature($parameters),
        ]));
    }

    /** The options this batch carries for one of its sync types. */
    public function parametersFor(string $syncType): array
    {
        return (array) data_get($this->parameters, $syncType, []);
    }

    public function covers(string $syncType): bool
    {
        return in_array($syncType, (array) $this->sync_types, true);
    }

    /** True when this batch was raised for exactly one sync type. */
    public function isSingleType(): bool
    {
        return count((array) $this->sync_types) === 1;
    }

    public function wasStarted(): bool
    {
        return $this->status !== self::STATUS_QUEUED;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_COMPLETED_WITH_FAILURES,
            self::STATUS_CANCELLED,
        ], true);
    }

    /** Re-read the run tallies off the runs themselves. */
    public function refreshCounts(): self
    {
        $counts = $this->runs()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $this->forceFill([
            'total_runs' => (int) $counts->sum(),
            'succeeded_runs' => (int) ($counts[GencysSyncRun::STATUS_SUCCESS] ?? 0),
            'failed_runs' => (int) ($counts[GencysSyncRun::STATUS_FAILED] ?? 0),
            'cancelled_runs' => (int) ($counts[GencysSyncRun::STATUS_CANCELLED] ?? 0),
        ])->save();

        return $this;
    }

    /** How far through its runs the batch is, 0–100. */
    public function progressPercent(): int
    {
        if ($this->total_runs < 1) {
            return 0;
        }

        $done = $this->succeeded_runs + $this->failed_runs + $this->cancelled_runs;

        return (int) round(($done / $this->total_runs) * 100);
    }

    public function scopeQueued(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_QUEUED);
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }
}
