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

    /** Open a pending run for one item + sync type. */
    public static function start(int $workspaceId, int $inventoryItemId, string $syncType, array $meta = []): self
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
     */
    public static function succeedById(int $workspaceId, ?int $syncRunId, int $rowsReceived, ?int $rowsSaved = null): void
    {
        if (! $syncRunId) {
            return;
        }

        self::query()
            ->where('workspace_id', $workspaceId)
            ->whereKey($syncRunId)
            ->first()
            ?->forceFill([
                'status' => self::STATUS_SUCCESS,
                'rows_received' => $rowsReceived,
                'rows_saved' => $rowsSaved ?? $rowsReceived,
                'finished_at' => now(),
            ])->save();
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
