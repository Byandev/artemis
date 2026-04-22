<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkspaceChecklist extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'created_by',
        'title',
        'target',
        'required',
    ];

    protected $casts = [
        'required' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completions(): HasMany
    {
        return $this->hasMany(WorkspaceChecklistCompletion::class, 'workspace_checklist_id');
    }
}
