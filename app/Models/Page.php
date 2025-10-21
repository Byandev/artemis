<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;

class Page extends Model
{
    public $guarded = [];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }


    public function scopeOfWorkspace($builder, Workspace $workspace)
    {
        return $builder->where('workspace_id', $workspace->id);
    }
}
