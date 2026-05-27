<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'workspace_id',
        'created_by',
        'name',
        'description',
        'due_date',
        'recurrence',
        'recurring_until',
        'recurring_from_task_id',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'recurring_until' => 'date',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recurringFrom(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'recurring_from_task_id');
    }

    public function recurringChildren(): HasMany
    {
        return $this->hasMany(Task::class, 'recurring_from_task_id');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }
}
