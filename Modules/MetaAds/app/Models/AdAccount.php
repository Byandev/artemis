<?php

namespace Modules\MetaAds\Models;

use App\Models\Concerns\ScopesToVisibleTeams;
use App\Models\Team;
use App\Models\User as AppUser;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\MetaAds\Services\MetaGraphClient;
use RuntimeException;

class AdAccount extends Model
{
    use ScopesToVisibleTeams;

    protected $table = 'meta_ads_accounts';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        // Facebook ad-account IDs are bigints that exceed JS Number.MAX_SAFE_INTEGER.
        // Cast to string so JSON keeps them exact for the frontend (keys/toggle-sync).
        'id' => 'string',
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

        $metaUser = $this->metaUsers()->whereNot('id', '1715720859559320')->first();
        if (! $metaUser) {
            throw new RuntimeException("No MetaUser linked to AdAccount {$this->id}");
        }

        return $metaUser->graphClient();
    }

    /**
     * The app user who owns this ad account. Nullable.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'owner_id');
    }

    public function metaUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meta_ads_user_account', 'meta_ads_account_id', 'meta_ads_user_id')
            ->withPivot('permitted_tasks')
            ->withTimestamps();
    }

    /**
     * Teams this ad account is assigned to. The pivot's access_level (view|manage)
     * determines whether a team's members may change the account or only view it.
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_ad_account', 'meta_ads_account_id', 'team_id')
            ->withPivot('access_level')
            ->withTimestamps();
    }

    /**
     * People Meta reports as having access to this account (Business Manager's
     * People list). Synced by SyncAdAccountPeople — unlike metaUsers(), this is
     * everyone with access, not just those who connected Artemis.
     */
    public function people(): HasMany
    {
        return $this->hasMany(AdAccountPerson::class, 'meta_ads_account_id');
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
