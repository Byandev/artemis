<?php

namespace Modules\GencysERP\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\InventoryItem;

/**
 * One attempt to sync a single subject — an inventory item, an intern, a page, a
 * date — from Gencys ERP.
 *
 * Runs belonging to a batch start `queued` and are flipped to `pending` when the
 * batch actually sends them to n8n. Runs dispatched outside a batch (the legacy
 * fire-and-forget path) go straight to `pending`. Either way `pending` means the
 * request is out and we're waiting on the callback: n8n posting the data back
 * flips it to `success`, a failed handshake or an expired timeout flips it to
 * `failed` — or back to `queued` for another attempt while the batch still has
 * retries left for it.
 *
 * `n8n_execution_id` is whatever execution handled the run, echoed back on the
 * callback. It is a tracing aid and nothing keys off it: when a run fails or
 * comes back short, it is what you paste into n8n to see what happened. It is
 * cleared on retry, since the next attempt is a different execution.
 */
class GencysSyncRun extends Model
{
    /** In a batch, waiting its turn. Not yet sent to n8n. */
    public const STATUS_QUEUED = 'queued';

    /** Sent to n8n, waiting on the callback. */
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    /** Its batch was cancelled before the run was ever sent. */
    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_TRANSACTION_HISTORY = 'transaction_history';

    public const TYPE_PURCHASE_ORDER = 'purchase_order';

    /** The workspace's intern roster, fetched whole rather than by window. */
    public const TYPE_INTERNS = 'interns';

    public const TYPE_INTERN_DAILY_RECORDS = 'intern_daily_records';

    public const TYPE_PAGE_DETAILS = 'page_details';

    public const TYPE_DAILY_SALES_TRACKER = 'daily_sales_tracker';

    protected $table = 'gencys_sync_runs';

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'sent_at' => 'datetime',
        'timeout_at' => 'datetime',
        'finished_at' => 'datetime',
        'meta' => 'array',
        'rows_received' => 'integer',
        'rows_saved' => 'integer',
        'attempt' => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(GencysSyncBatch::class, 'gencys_sync_batch_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * Open a pending run for one sync type. $inventoryItemId is the subject for
     * inventory syncs; intern syncs pass null and keep the intern id in $meta.
     *
     * This is the un-batched path: the run is pending the moment it's created
     * because the caller sends it to n8n immediately.
     */
    public static function start(int $workspaceId, ?int $inventoryItemId, string $syncType, array $meta = []): self
    {
        return self::create([
            'workspace_id' => $workspaceId,
            'inventory_item_id' => $inventoryItemId,
            'sync_type' => $syncType,
            'status' => self::STATUS_PENDING,
            'started_at' => now(),
            'sent_at' => now(),
            'meta' => $meta ?: null,
        ]);
    }

    /**
     * Open a queued run belonging to a batch. Nothing is sent yet — the batch
     * decides when this run's group goes out.
     */
    public static function queue(
        GencysSyncBatch $batch,
        int $workspaceId,
        ?int $inventoryItemId,
        string $syncType,
        string $groupKey,
        array $meta = [],
    ): self {
        return self::create([
            'workspace_id' => $workspaceId,
            'gencys_sync_batch_id' => $batch->id,
            'inventory_item_id' => $inventoryItemId,
            'sync_type' => $syncType,
            'group_key' => $groupKey,
            'status' => self::STATUS_QUEUED,
            'started_at' => now(),
            'meta' => $meta ?: null,
        ]);
    }

    /**
     * Mark the run n8n echoed back as succeeded. A missing or unknown id is a
     * no-op — the run stays pending and the batch's timeout handles it.
     *
     * A success also clears the earlier runs that asked for exactly the same
     * thing — see resolveEarlierRunsWithSameParameters().
     */
    public static function succeedById(
        int $workspaceId,
        ?int $syncRunId,
        int $rowsReceived,
        ?int $rowsSaved = null,
        ?string $executionId = null,
    ): void {
        $run = self::lookup($workspaceId, $syncRunId);

        if (! $run) {
            return;
        }

        $run->forceFill([
            'status' => self::STATUS_SUCCESS,
            'rows_received' => $rowsReceived,
            'rows_saved' => $rowsSaved ?? $rowsReceived,
            'finished_at' => now(),
            'n8n_execution_id' => $executionId ?? $run->n8n_execution_id,
        ])->save();

        $run->resolveEarlierRunsWithSameParameters();

        // Deliberately no BatchRunner::tick() here: a bulk callback resolves many
        // runs in one request and the batch can't move until the last of them
        // lands, so the controllers tick once when they're done rather than once
        // per item.
    }

    /** Find one of this workspace's runs by the id n8n echoed back. */
    private static function lookup(int $workspaceId, ?int $syncRunId): ?self
    {
        if (! $syncRunId) {
            return null;
        }

        return self::query()
            ->where('workspace_id', $workspaceId)
            ->whereKey($syncRunId)
            ->first();
    }

    /**
     * The run a callback belongs to when n8n didn't echo an id back.
     *
     * Only safe for the sync types a batch sends one subject at a time — the
     * transaction-history report covers a whole date, so a workspace never has
     * two of them in flight, and the oldest one still waiting is the one this
     * callback answers. Types that go out in groups must echo the id instead.
     */
    public static function oldestInFlight(int $workspaceId, string $syncType): ?self
    {
        return self::query()
            ->where('workspace_id', $workspaceId)
            ->where('sync_type', $syncType)
            ->pending()
            ->orderBy('id')
            ->first();
    }

    /**
     * Record one chunk of a run that arrives in pieces, without closing it.
     *
     * Some ERP pulls are too big for a single callback — the daily sales tracker
     * comes back a thousand rows at a time — so the row counts accumulate and the
     * callback deadline is pushed out rather than the run being marked done. The
     * run stays `pending` until something calls finishById(), which is what lets
     * the batch keep holding the ERP until n8n is genuinely finished.
     *
     * Pushing timeout_at forward is the important part: without it a long sync
     * would be failed by the sweeper mid-stream and retried from the top.
     */
    public static function heartbeatById(
        int $workspaceId,
        ?int $syncRunId,
        int $rowsReceived,
        ?int $rowsSaved = null,
        ?string $executionId = null,
    ): ?self {
        $run = self::lookup($workspaceId, $syncRunId);

        // Only a run still in flight can be fed. A chunk arriving for a run that
        // already finished (a late retry, a duplicate post) is ignored rather
        // than reopening it.
        if (! $run || $run->status !== self::STATUS_PENDING) {
            return $run;
        }

        $run->forceFill([
            'rows_received' => $run->rows_received + $rowsReceived,
            'rows_saved' => $run->rows_saved + ($rowsSaved ?? $rowsReceived),
            'timeout_at' => now()->addSeconds($run->timeoutSeconds()),
            'n8n_execution_id' => $executionId ?? $run->n8n_execution_id,
        ])->save();

        return $run;
    }

    /**
     * Close a chunked run out. Row counts default to whatever the chunks
     * accumulated, so a caller that doesn't track totals can just say "done".
     *
     * Idempotent: finishing an already-finished run leaves it alone.
     */
    public static function finishById(
        int $workspaceId,
        ?int $syncRunId,
        ?int $rowsReceived = null,
        ?int $rowsSaved = null,
        bool $failed = false,
        ?string $message = null,
        ?string $executionId = null,
    ): ?self {
        $run = self::lookup($workspaceId, $syncRunId);

        if (! $run || $run->isFinished()) {
            return $run;
        }

        // Stamped before the branch so a failure is just as traceable as a success.
        if ($executionId) {
            $run->forceFill(['n8n_execution_id' => $executionId])->save();
        }

        if ($failed) {
            $run->fail($message ?: 'Reported as failed by n8n.');

            return $run;
        }

        $run->forceFill([
            'status' => self::STATUS_SUCCESS,
            'rows_received' => $rowsReceived ?? $run->rows_received,
            'rows_saved' => $rowsSaved ?? $rowsReceived ?? $run->rows_saved,
            'finished_at' => now(),
            'timeout_at' => null,
            'message' => $message,
        ])->save();

        $run->resolveEarlierRunsWithSameParameters();

        return $run;
    }

    /** How long this run waits on a callback — its batch's setting, or the default. */
    public function timeoutSeconds(): int
    {
        return (int) ($this->batch?->timeout_seconds ?? config('gencyserp.batch.timeout_seconds', 600));
    }

    /**
     * Flip earlier pending/failed runs that asked the ERP for exactly the same
     * thing to success.
     *
     * Retries and the several-times-daily schedule mean the same item/date is
     * fetched again and again. Once one of those attempts comes back, the data
     * those earlier attempts were waiting on is in the database — a run left
     * pending (no callback) or failed (n8n handshake, timeout) is stale
     * bookkeeping, not a data gap, so it's resolved rather than left glaring on
     * the Sync Runs page.
     *
     * "Same thing" is same workspace + sync type + inventory item + identical
     * meta (the parameters we sent n8n: date, date range, intern id, page id).
     * Only lower ids are touched — a run opened after this one is a separate,
     * still-outstanding attempt. Queued runs are left alone: they haven't been
     * sent, so they're future work, not stale bookkeeping.
     *
     * Returns the batch ids of the runs it resolved.
     *
     * @return array<int, int>
     */
    public function resolveEarlierRunsWithSameParameters(): array
    {
        $signature = self::parameterSignature($this->meta);

        $batchIds = [];

        self::query()
            ->where('workspace_id', $this->workspace_id)
            ->where('sync_type', $this->sync_type)
            ->when(
                $this->inventory_item_id === null,
                fn (Builder $query) => $query->whereNull('inventory_item_id'),
                fn (Builder $query) => $query->where('inventory_item_id', $this->inventory_item_id),
            )
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_FAILED])
            ->where('id', '<', $this->id)
            ->get()
            ->each(function (self $earlier) use ($signature, &$batchIds) {
                if (self::parameterSignature($earlier->meta) !== $signature) {
                    return;
                }

                $earlier->forceFill([
                    'status' => self::STATUS_SUCCESS,
                    // The counts of the run that actually brought the data back.
                    'rows_received' => $this->rows_received,
                    'rows_saved' => $this->rows_saved,
                    'finished_at' => now(),
                    'timeout_at' => null,
                    'message' => "Resolved by sync run #{$this->id}, which fetched the same data.",
                ])->save();

                $batchIds[] = $earlier->gencys_sync_batch_id;
            });

        return array_values(array_unique(array_filter($batchIds)));
    }

    /**
     * A stable string for a run's parameters, so two runs can be compared
     * regardless of the key order their meta happened to be written in.
     */
    public static function parameterSignature(?array $meta): string
    {
        $normalise = function (array $values) use (&$normalise): array {
            ksort($values);

            foreach ($values as $key => $value) {
                if (is_array($value)) {
                    $values[$key] = $normalise($value);
                }
            }

            return $values;
        };

        return json_encode($normalise($meta ?? []));
    }

    /** Record that this run has gone out to n8n and start its callback clock. */
    public function markSent(int $timeoutSeconds): void
    {
        $this->forceFill([
            'status' => self::STATUS_PENDING,
            'sent_at' => now(),
            'timeout_at' => now()->addSeconds($timeoutSeconds),
        ])->save();
    }

    /**
     * Put the run back in its batch's queue for another attempt. Retries go out
     * on their own rather than back into a group, so one bad subject can't keep
     * taking its neighbours down with it.
     */
    public function requeueForRetry(string $message): void
    {
        $this->forceFill([
            'status' => self::STATUS_QUEUED,
            'attempt' => $this->attempt + 1,
            'sent_at' => null,
            'timeout_at' => null,
            // A retry is a fresh n8n execution; keeping the old id would point
            // at the run that already gave up.
            'n8n_execution_id' => null,
            'message' => $message,
        ])->save();
    }

    public function fail(string $message): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'finished_at' => now(),
            'timeout_at' => null,
            'message' => $message,
        ])->save();
    }

    public function cancel(string $message): void
    {
        $this->forceFill([
            'status' => self::STATUS_CANCELLED,
            'finished_at' => now(),
            'timeout_at' => null,
            'message' => $message,
        ])->save();
    }

    /** True once the run has an outcome and the batch can stop waiting on it. */
    public function isFinished(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUCCESS,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ], true);
    }

    public function scopeQueued(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_QUEUED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
