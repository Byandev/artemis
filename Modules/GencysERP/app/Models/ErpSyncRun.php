<?php

namespace Modules\GencysERP\Models;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single ERP fetch run (transaction history or purchase orders) for one
 * workspace. Owns the dispatched chunks and rolls their statuses up.
 */
class ErpSyncRun extends Model
{
    public const TYPE_TRANSACTION_HISTORY = 'transaction_history';

    public const TYPE_PURCHASE_ORDER = 'purchase_order';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    public const TRIGGER_SCHEDULE = 'schedule';

    public const TRIGGER_MANUAL = 'manual';

    public const TRIGGER_RETRY = 'retry';

    protected $table = 'gencys_erp_sync_runs';

    protected $guarded = [];

    protected $casts = [
        'params' => 'array',
        'total_items' => 'integer',
        'total_chunks' => 'integer',
        'items_synced' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(ErpSyncChunk::class, 'gencys_erp_sync_run_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    /**
     * Recompute the run status from its chunks. Called whenever a chunk changes
     * state (job result or callback). Runs a fresh aggregate so concurrent chunk
     * jobs converge on the right answer.
     */
    public function recalculateStatus(): void
    {
        $counts = $this->chunks()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = (int) $counts->sum();
        $pending = (int) ($counts[ErpSyncChunk::STATUS_PENDING] ?? 0);
        $failed = (int) ($counts[ErpSyncChunk::STATUS_FAILED] ?? 0);

        $status = match (true) {
            $total === 0 => self::STATUS_RUNNING,
            $pending > 0 => self::STATUS_RUNNING,
            $failed === $total => self::STATUS_FAILED,
            $failed > 0 => self::STATUS_PARTIAL,
            default => self::STATUS_COMPLETED,
        };

        $this->forceFill([
            'status' => $status,
            'items_synced' => (int) $this->chunks()->sum('items_synced'),
            // Stamp once the run settles; clear it again if a retry re-opens a chunk.
            'finished_at' => $pending > 0 ? null : ($this->finished_at ?? now()),
        ])->save();
    }
}
