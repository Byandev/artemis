<?php

namespace Modules\MetaAds\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\MetaAds\Services\MetaGraphClient;
use RuntimeException;

class AdAccount extends Model
{
    protected $table = 'meta_ads_accounts';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'uses_system_user' => 'boolean',
        'active_sync' => 'boolean',
    ];

    public function graphAccountId(): string
    {
        return 'act_'.$this->id;
    }

    public function graphClient(): MetaGraphClient
    {
        if ($this->uses_system_user) {
            $token = config('metaads.system_user_token');
            if (! $token) {
                throw new RuntimeException("AdAccount {$this->id} is flagged uses_system_user but META_ADS_SYSTEM_USER_TOKEN is not set");
            }

            return new MetaGraphClient($token);
        }

        $metaUser = $this->metaUsers()->first();
        if (! $metaUser) {
            throw new RuntimeException("No MetaUser linked to AdAccount {$this->id}");
        }

        return $metaUser->graphClient();
    }

    public function metaUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meta_ads_user_account', 'meta_ads_account_id', 'meta_ads_user_id')
            ->withPivot('permitted_tasks')
            ->withTimestamps();
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'meta_ads_account_id');
    }

    public function adSets(): HasMany
    {
        return $this->hasMany(AdSet::class, 'meta_ads_account_id');
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class, 'meta_ads_account_id');
    }

    public function creatives(): HasMany
    {
        return $this->hasMany(Creative::class, 'meta_ads_account_id');
    }

    public function insights(): HasMany
    {
        return $this->hasMany(Insight::class, 'meta_ads_account_id');
    }

    public function scopeForWorkspace(Builder $query, Workspace $workspace): Builder
    {
        return $query->whereHas('metaUsers.workspaces', function ($q) use ($workspace) {
            $q->where('workspaces.id', $workspace->id);
        });
    }
}
