<?php

namespace Modules\GencysERP\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One scheduled sweep of Gencys ERP for a single workspace — the three sync
 * types (transaction history, daily sales tracker, purchase orders) fired
 * together at 09:30, 14:00 or 19:00 and tracked as a unit.
 *
 * A batch exists because the individual runs finish asynchronously: Laravel
 * hands a webhook to n8n in milliseconds, but the scrape and its callback take
 * minutes. `withoutOverlapping()` on the scheduler only guards the command
 * process, so it cannot see that outstanding round-trip. The batch can — it
 * stays `running` until the last callback lands, which is what lets the next
 * slot skip a workspace that hasn't finished yet.
 */
class GencysSyncBatch extends Model
{
    /** Dispatched, still waiting on at least one n8n callback. */
    public const STATUS_RUNNING = 'running';

    /** Every run resolved, none failed. */
    public const STATUS_COMPLETED = 'completed';

    /** Every run resolved, some failed. */
    public const STATUS_PARTIAL = 'partial';

    /** Every run failed. */
    public const STATUS_FAILED = 'failed';

    /** Never dispatched — the previous batch for this workspace was still running. */
    public const STATUS_SKIPPED = 'skipped';

    public const TRIGGER_SCHEDULE = 'schedule';

    public const TRIGGER_MANUAL = 'manual';

    public const TRIGGER_RETRY = 'retry';

    /** The sync types a scheduled batch covers, in dispatch order. */
    public const DEFAULT_SYNC_TYPES = [
        GencysSyncRun::TYPE_TRANSACTION_HISTORY,
        GencysSyncRun::TYPE_DAILY_SALES_TRACKER,
        GencysSyncRun::TYPE_PURCHASE_ORDER,
    ];

    protected $table = 'gencys_sync_batches';

    protected $guarded = [];

    protected $casts = [
        'sync_types' => 'array',
        'meta' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'total_runs' => 'integer',
        'completed_runs' => 'integer',
        'failed_runs' => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(GencysSyncRun::class, 'batch_id');
    }

    /** Open a batch and start counting. Runs attach to it as they're created. */
    public static function start(
        int $workspaceId,
        array $syncTypes = self::DEFAULT_SYNC_TYPES,
        string $trigger = self::TRIGGER_SCHEDULE,
        array $meta = [],
    ): self {
        return self::create([
            'workspace_id' => $workspaceId,
            'trigger' => $trigger,
            'status' => self::STATUS_RUNNING,
            'sync_types' => $syncTypes,
            'started_at' => now(),
            'meta' => $meta ?: null,
        ]);
    }

    /**
     * Record that a workspace was passed over because its previous batch hadn't
     * finished. Written rather than silently skipped so Sync Health can show
     * *why* a slot produced no data — a silent gap looks identical to an outage.
     */
    public static function skip(int $workspaceId, self $blockedBy): self
    {
        return self::create([
            'workspace_id' => $workspaceId,
            'trigger' => self::TRIGGER_SCHEDULE,
            'status' => self::STATUS_SKIPPED,
            'started_at' => now(),
            'finished_at' => now(),
            'message' => "Skipped — batch #{$blockedBy->id} from ".
                $blockedBy->started_at?->format('H:i').
                ' is still running ('.$blockedBy->outstandingRuns().' run(s) outstanding).',
            'meta' => ['blocked_by_batch_id' => $blockedBy->id],
        ]);
    }

    /**
     * The batch currently blocking new work for this workspace, if any.
     *
     * Only `running` blocks. A partial or failed batch is finished — its runs
     * are all resolved — so the next slot should go ahead and try again rather
     * than stall behind a batch that will never change on its own.
     */
    public static function openFor(int $workspaceId): ?self
    {
        return self::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', self::STATUS_RUNNING)
            ->orderByDesc('id')
            ->first();
    }

    public function outstandingRuns(): int
    {
        return $this->runs()->where('status', GencysSyncRun::STATUS_PENDING)->count();
    }

    /**
     * Recount this batch's runs and close it if nothing is outstanding.
     *
     * Called whenever a run changes state (callback, handshake failure, stale
     * sweeper) so the batch closes the moment its last run resolves. Idempotent
     * — safe to call from every one of those paths.
     */
    public function refreshCounters(): self
    {
        // Skipped batches own no runs and must not be revived by a recount.
        if ($this->status === self::STATUS_SKIPPED) {
            return $this;
        }

        $counts = $this->runs()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $pending = (int) ($counts[GencysSyncRun::STATUS_PENDING] ?? 0);
        $success = (int) ($counts[GencysSyncRun::STATUS_SUCCESS] ?? 0);
        $failed = (int) ($counts[GencysSyncRun::STATUS_FAILED] ?? 0);
        $total = $pending + $success + $failed;

        $resolved = $pending === 0;

        $status = match (true) {
            ! $resolved => self::STATUS_RUNNING,
            // A batch that dispatched nothing (no eligible items) is done, not stuck.
            $total === 0, $failed === 0 => self::STATUS_COMPLETED,
            $success === 0 => self::STATUS_FAILED,
            default => self::STATUS_PARTIAL,
        };

        $this->forceFill([
            'total_runs' => $total,
            'completed_runs' => $success,
            'failed_runs' => $failed,
            'status' => $status,
            // Reopened by a retry: clear the old finish time so duration stays honest.
            'finished_at' => $resolved ? ($this->finished_at ?? now()) : null,
        ])->save();

        return $this;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RUNNING);
    }
}
