<?php

namespace Modules\MetaAds\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\MetaAds\Services\MetaGraphClient;

class User extends Model
{
    protected $table = 'meta_ads_users';

    protected $guarded = [];

    protected $casts = [
        'access_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
    ];

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'meta_ads_workspace_user', 'meta_ads_user_id', 'workspace_id')
            ->withPivot('connected_by_user_id')
            ->withTimestamps();
    }

    public function adAccounts(): BelongsToMany
    {
        return $this->belongsToMany(AdAccount::class, 'meta_ads_user_account', 'meta_ads_user_id', 'meta_ads_account_id')
            ->withPivot('permitted_tasks')
            ->withTimestamps();
    }

    public function graphClient(): MetaGraphClient
    {
        return new MetaGraphClient($this->access_token);
    }
}
