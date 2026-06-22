<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\MetaAds\Models\AdAccount;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'discord_webhook_url',
    ];

    /**
     * Get the workspace that owns the team.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the users/members of this team.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_user');
    }

    /**
     * Pages assigned to this team (team-level data ownership).
     */
    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class, 'team_page')->withTimestamps();
    }

    /**
     * Ad accounts assigned to this team. The pivot's access_level (view|manage)
     * controls whether members may only see the account or also change it.
     */
    public function adAccounts(): BelongsToMany
    {
        return $this->belongsToMany(
            AdAccount::class,
            'team_ad_account',
            'team_id',
            'meta_ads_account_id',
        )->withPivot('access_level')->withTimestamps();
    }

    /**
     * Scope to filter teams by workspace.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(TeamMemberSchedule::class);
    }

    public function scopeOfWorkspace($query, Workspace $workspace)
    {
        return $query->where('workspace_id', $workspace->id);
    }
}
