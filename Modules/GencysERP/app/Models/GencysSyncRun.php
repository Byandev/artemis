<?php

namespace Modules\GencysERP\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Inventory\Models\InventoryItem;

/**
 * One attempt to sync a single inventory item from Gencys ERP through the n8n
 * round trip. The lifecycle is:
 *
 *   pending  → created by the trigger command when the sync is dispatched
 *   success  → n8n posted the data back and the callback saved it
 *   skipped  → callback arrived but there was nothing to record
 *   failed   → the outbound webhook handshake failed, OR no callback ever
 *              arrived (the stale-run sweeper flips long-pending runs to failed)
 *
 * This is what lets the Sync Health view answer "did each item actually sync?"
 * — including the silent case where the ERP login fails inside n8n and nothing
 * is ever posted back.
 */
class GencysSyncRun extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const TYPE_TRANSACTION_HISTORY = 'transaction_history';

    public const TYPE_PURCHASE_ORDER = 'purchase_order';

    protected $table = 'gencys_sync_runs';

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'meta' => 'array',
        'rows_received' => 'integer',
        'rows_saved' => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /** Open a pending run for one item + sync type. */
    public static function start(int $workspaceId, ?int $inventoryItemId, string $syncType, array $meta = []): self
    {
        return self::create([
            'workspace_id' => $workspaceId,
            'inventory_item_id' => $inventoryItemId,
            'sync_type' => $syncType,
            'status' => self::STATUS_PENDING,
            'started_at' => Carbon::now(),
            'meta' => $meta ?: null,
        ]);
    }

    public function succeed(int $rowsReceived, int $rowsSaved, array $meta = []): void
    {
        $this->forceFill([
            'status' => self::STATUS_SUCCESS,
            'rows_received' => $rowsReceived,
            'rows_saved' => $rowsSaved,
            'finished_at' => Carbon::now(),
            'meta' => array_merge((array) $this->meta, $meta) ?: null,
        ])->save();
    }

    public function skip(string $message, array $meta = []): void
    {
        $this->forceFill([
            'status' => self::STATUS_SKIPPED,
            'finished_at' => Carbon::now(),
            'message' => $message,
            'meta' => array_merge((array) $this->meta, $meta) ?: null,
        ])->save();
    }

    public function fail(string $message, array $meta = []): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'finished_at' => Carbon::now(),
            'message' => $message,
            'meta' => array_merge((array) $this->meta, $meta) ?: null,
        ])->save();
    }

    /**
     * The most recent still-pending run for an item + type, so a callback can
     * resolve it. Returns null when nothing is outstanding.
     */
    public static function latestPending(int $workspaceId, int $inventoryItemId, string $syncType): ?self
    {
        return self::query()
            ->where('workspace_id', $workspaceId)
            ->where('inventory_item_id', $inventoryItemId)
            ->where('sync_type', $syncType)
            ->where('status', self::STATUS_PENDING)
            ->latest('id')
            ->first();
    }

    /**
     * Find the run a callback refers to. We send each run's id to n8n in the
     * payload and n8n echoes it back, so the exact id (scoped to the workspace)
     * is the precise match — that also makes a replayed callback idempotent.
     * When no id comes back we fall back to the item's latest pending run.
     */
    public static function resolveFor(int $workspaceId, ?int $syncRunId, ?int $inventoryItemId, string $syncType): ?self
    {
        if ($syncRunId) {
            $run = self::query()
                ->where('workspace_id', $workspaceId)
                ->whereKey($syncRunId)
                ->first();

            if ($run) {
                return $run;
            }
        }

        return $inventoryItemId
            ? self::latestPending($workspaceId, $inventoryItemId, $syncType)
            : null;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
