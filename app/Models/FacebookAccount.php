<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FacebookAccount extends Model
{
    protected $table = 'facebook_accounts';

    protected $guarded = [];

    public function scopeOfWorkspace($builder, Workspace $workspace)
    {
        return $builder->whereHas('workspaces', function ($query) use ($workspace) {
            return $query->where('workspace_id', $workspace->id);
        });
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_facebook_account');
    }

    public function adAccounts(): BelongsToMany
    {
        return $this->belongsToMany(AdAccount::class, 'facebook_account_ad_account');
    }
}
