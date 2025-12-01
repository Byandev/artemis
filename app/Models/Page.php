<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    
    public function scopeOfWorkspace($builder, Workspace $workspace)
    {
        return $builder->where('workspace_id', $workspace->id);
    }

    public function scopeFilter($query, array $filters)
    {
        if (!empty($filters['owner_id'])) {
            $query->where('owner_id', $filters['owner_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

}
