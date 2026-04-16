<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WorkspaceChecklistCompletion extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'workspace_checklist_id',
        'target_type',
        'target_id',
        'checked_by',
        'checked_at',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(WorkspaceChecklist::class, 'workspace_checklist_id');
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
