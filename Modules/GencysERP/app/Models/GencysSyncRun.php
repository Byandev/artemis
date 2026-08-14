<?php

namespace Modules\GencysERP\Models;

use App\Models\Concerns\ScopesToVisibleTeams;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\InventoryItem;

/**
 * One attempt to sync a single inventory item from Gencys ERP. Created as
 * `pending` when the sync is dispatched, then flipped to `success` when n8n
 * posts the data back (matched by the run id it echoes), to `failed` if the
 * outbound webhook handshake fails, or to `failed` by the stale-run sweeper
 * when no callback ever arrives.
 */
class GencysSyncRun extends Model
{
    use ScopesToVisibleTeams;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const TYPE_TRANSACTION_HISTORY = 'transaction_history';

    public const TYPE_PURCHASE_ORDER = 'purchase_order';

    public const TYPE_INTERN_DAILY_RECORDS = 'intern_daily_records';

    public const TYPE_PAGE_DETAILS = 'page_details';

    public const TYPE_DAILY_SALES_TRACKER = 'daily_sales_tracker';

    protected $table = 'gencys_sync_runs';

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'meta' => 'array',
        'rows_received' => 'integer',
        'rows_saved' => 'integer',
        'n8n_execution_id' => 'integer',
    ];

    /** Team visibility flows through the run's inventory item. */
    protected function visibilityTeamRelation(): string
    {
        return 'inventoryItem.product.shops.teams';
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /** The scheduled sweep this run belongs to; null for the older per-type commands. */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(GencysSyncBatch::class, 'batch_id');
    }

    /**
     * Open a pending run for one sync type. $inventoryItemId is the subject for
     * inventory syncs; intern syncs pass null and keep the intern id in $meta.
     *
     * $batchId ties the run to a scheduled sweep so the batch knows what it's
     * still waiting on. Runs opened outside a batch pass null.
     */
    public static function start(
        int $workspaceId,
        ?int $inventoryItemId,
        string $syncType,
        array $meta = [],
        ?int $batchId = null,
    ): self {
        return self::create([
            'workspace_id' => $workspaceId,
            'batch_id' => $batchId,
            'inventory_item_id' => $inventoryItemId,
            'sync_type' => $syncType,
            'status' => self::STATUS_PENDING,
            'started_at' => now(),
            'meta' => $meta ?: null,
        ]);
    }

    /**
     * Mark the run n8n echoed back as succeeded. A missing or unknown id is a
     * no-op — the run stays pending and the stale sweeper handles it.
     *
     * A success also clears the earlier runs that asked for exactly the same
     * thing — see resolveEarlierRunsWithSameParameters().
     */
    public static function succeedById(
        int $workspaceId,
        ?int $syncRunId,
        int $rowsReceived,
        ?int $rowsSaved = null,
        ?int $n8nExecutionId = null,
    ): void {
        if (! $syncRunId) {
            return;
        }

        $run = self::query()
            ->where('workspace_id', $workspaceId)
            ->whereKey($syncRunId)
            ->first();

        if (! $run) {
            return;
        }

        $run->forceFill(array_filter([
            'status' => self::STATUS_SUCCESS,
            'rows_received' => $rowsReceived,
            'rows_saved' => $rowsSaved ?? $rowsReceived,
            'finished_at' => now(),
            // Only overwrite when the callback actually carried one, so a flow
            // that hasn't been updated to send it doesn't blank an existing id.
            'n8n_execution_id' => $n8nExecutionId,
        ], fn ($value) => $value !== null))->save();

        $run->resolveEarlierRunsWithSameParameters();
        $run->refreshBatch();
    }

    /**
     * Recount this run's batch after a state change, so it closes as soon as its
     * last run resolves.
     *
     * Runs outside a batch are a no-op.
     */
    public function refreshBatch(): void
    {
        $this->batch?->refreshCounters();
    }

    /**
     * Flip earlier pending/failed runs that asked the ERP for exactly the same
     * thing to success.
     *
     * Retries and the thrice-daily schedule mean the same item/date is fetched
     * again and again. Once one of those attempts comes back, the data those
     * earlier attempts were waiting on is in the database — a run left pending
     * (no callback) or failed (n8n handshake, stale sweeper) is stale bookkeeping,
     * not a data gap, so it's resolved rather than left glaring on Sync Health.
     *
     * "Same thing" is same workspace + sync type + inventory item + identical
     * meta (the parameters we sent n8n: date, date range, intern id, page id).
     * Only lower ids are touched — a run opened after this one is a separate,
     * still-outstanding attempt.
     *
     * Returns the number of runs resolved.
     */
    public function resolveEarlierRunsWithSameParameters(): int
    {
        $signature = self::parameterSignature($this->meta);

        $resolved = 0;

        // Batches owning the runs we just closed. Those runs belong to *earlier*
        // sweeps, so without recounting here their batch would sit at `running`
        // with nothing left to wait for — and the overlap guard would refuse to
        // dispatch that workspace ever again.
        $touchedBatchIds = [];

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
            ->each(function (self $earlier) use ($signature, &$resolved, &$touchedBatchIds) {
                if (self::parameterSignature($earlier->meta) !== $signature) {
                    return;
                }

                $earlier->forceFill([
                    'status' => self::STATUS_SUCCESS,
                    // The counts of the run that actually brought the data back.
                    'rows_received' => $this->rows_received,
                    'rows_saved' => $this->rows_saved,
                    'finished_at' => now(),
                    'message' => "Resolved by sync run #{$this->id}, which fetched the same data.",
                ])->save();

                if ($earlier->batch_id) {
                    $touchedBatchIds[$earlier->batch_id] = true;
                }

                $resolved++;
            });

        GencysSyncBatch::query()
            ->whereKey(array_keys($touchedBatchIds))
            ->get()
            ->each
            ->refreshCounters();

        return $resolved;
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

    /**
     * Pull the n8n execution id out of a callback entry, tolerating the key
     * spellings the different flows use.
     *
     * The whole point of storing it is that a failed run can be opened straight
     * in n8n's execution log, so it's read leniently — a missing id costs us
     * traceability, but a wrong strict match costs us the id entirely.
     */
    public static function executionIdFrom(array $entry): ?int
    {
        $id = $entry['n8n_execution_id']
            ?? $entry['execution_id']
            ?? $entry['executionId']
            ?? null;

        // n8n sends its execution id as a number, but an expression that
        // stringifies it shouldn't cost us the id — accept numeric strings too
        // and reject anything that isn't a number outright.
        if ($id === null || $id === '' || ! is_numeric($id)) {
            return null;
        }

        return (int) $id;
    }

    /**
     * Put a resolved run back to pending because its n8n execution is being
     * replayed. The retried execution re-posts the original payload, so it
     * carries this same run id and will close this row out again.
     *
     * started_at is reset deliberately: the stale sweeper measures from it, and
     * without the reset a run retried hours later would be failed again on the
     * sweeper's very next pass, before n8n had a chance to report.
     */
    public function reopen(string $message): void
    {
        $this->forceFill([
            'status' => self::STATUS_PENDING,
            'started_at' => now(),
            'finished_at' => null,
            'message' => $message,
        ])->save();

        $this->refreshBatch();
    }

    public function fail(string $message): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'finished_at' => now(),
            'message' => $message,
        ])->save();

        $this->refreshBatch();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
