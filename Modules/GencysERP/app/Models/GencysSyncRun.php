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

    /**
     * Open a pending run for one sync type. $inventoryItemId is the subject for
     * inventory syncs; intern syncs pass null and keep the intern id in $meta.
     */
    public static function start(int $workspaceId, ?int $inventoryItemId, string $syncType, array $meta = []): self
    {
        return self::create([
            'workspace_id' => $workspaceId,
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
    public static function succeedById(int $workspaceId, ?int $syncRunId, int $rowsReceived, ?int $rowsSaved = null): void
    {
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

        $run->forceFill([
            'status' => self::STATUS_SUCCESS,
            'rows_received' => $rowsReceived,
            'rows_saved' => $rowsSaved ?? $rowsReceived,
            'finished_at' => now(),
        ])->save();

        $run->resolveEarlierRunsWithSameParameters();
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
            ->each(function (self $earlier) use ($signature, &$resolved) {
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

                $resolved++;
            });

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

    public function fail(string $message): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'finished_at' => now(),
            'message' => $message,
        ])->save();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
