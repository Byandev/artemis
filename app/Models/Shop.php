<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Shop extends Model
{
    use HasFactory;

    public $guarded = [];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function checklistCompletions(): MorphMany
    {
        return $this->morphMany(WorkspaceChecklistCompletion::class, 'target');
    }
}
