<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AdAccount extends Model
{
    protected $guarded = [];

    public function facebook_accounts(): BelongsToMany
    {
        return $this->belongsToMany(FacebookAccount::class, 'facebook_account_ad_account');
    }

    public function scopeOfWorkspace($builder, Workspace $workspace)
    {
        return $builder->whereHas('facebook_accounts', function ($query) use ($workspace) {
            return $query->whereHas('workspaces', function ($subQuery) use ($workspace) {
                return $subQuery->where('workspace_id', $workspace->id);
            });
        });
    }

    public function adRecords(): HasMany
    {
        return $this->hasMany(AdRecord::class);
    }
}
