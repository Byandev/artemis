<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParcelJourney extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function notifications(): ParcelJourney|\Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ParcelJourneyNotification::class);
    }

    public function scopeOfWorkspace(Builder $builder, Workspace $workspace): Builder
    {
        return $builder->where('workspace_id', $workspace->id);
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
}
