<?php

namespace App\Models;

use Database\Factories\DailyTrackerCompletionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A ticked box: one tracked member's deliverable, done for one period.
 *
 * Rows only exist for completed deliverables — unticking deletes the row rather
 * than storing a false, so the board reads "done" as presence.
 */
class DailyTrackerCompletion extends Model
{
    /** @use HasFactory<DailyTrackerCompletionFactory> */
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'daily_tracker_item_id',
        'user_id',
        'tracked_on',
        'checked_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'tracked_on' => 'immutable_date',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(DailyTrackerItem::class, 'daily_tracker_item_id');
    }

    /** The member the deliverable belongs to. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Who ticked it — a lead ticking for someone else is not that someone. */
    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
