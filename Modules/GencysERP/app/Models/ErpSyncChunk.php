<?php

namespace Modules\GencysERP\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One dispatched job: the chunk of inventory items n8n fetches in a single ERP
 * session. Tracks the webhook outcome and (when n8n echoes the chunk id back on
 * the callback) the records actually synced.
 */
class ErpSyncChunk extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';        // webhook accepted by n8n, awaiting callback

    public const STATUS_CONFIRMED = 'confirmed'; // n8n called back with results

    public const STATUS_FAILED = 'failed';    // webhook POST failed — retryable

    protected $table = 'gencys_erp_sync_chunks';

    protected $guarded = [];

    protected $casts = [
        'item_ids' => 'array',
        'item_count' => 'integer',
        'attempts' => 'integer',
        'webhook_status' => 'integer',
        'items_synced' => 'integer',
        'dispatched_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(ErpSyncRun::class, 'gencys_erp_sync_run_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** The job is about to POST to n8n — bump the attempt counter. */
    public function markDispatched(): void
    {
        $this->forceFill([
            'attempts' => $this->attempts + 1,
            'dispatched_at' => now(),
        ])->save();
    }

    /** The webhook returned 2xx — n8n accepted the job, callback pending. */
    public function markSent(?int $status): void
    {
        $this->forceFill([
            'status' => self::STATUS_SENT,
            'webhook_status' => $status,
            'error_message' => null,
        ])->save();

        $this->run?->recalculateStatus();
    }

    /** The webhook POST failed (non-2xx or threw) — this is what gets retried. */
    public function markFailed(?int $status, ?string $error): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'webhook_status' => $status,
            'error_message' => $error ? mb_substr($error, 0, 1000) : null,
        ])->save();

        $this->run?->recalculateStatus();
    }

    /** n8n called back (echoing this chunk id) with the records it synced. */
    public function markConfirmed(int $itemsSynced): void
    {
        $this->forceFill([
            'status' => self::STATUS_CONFIRMED,
            'items_synced' => $itemsSynced,
            'confirmed_at' => now(),
            'error_message' => null,
        ])->save();

        $this->run?->recalculateStatus();
    }
}
