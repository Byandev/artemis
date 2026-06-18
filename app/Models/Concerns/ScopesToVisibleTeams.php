<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Models\Workspace;
use App\Support\TeamVisibility;
use Illuminate\Database\Eloquent\Builder;

/**
 * Adds a `visibleTo($user, $workspace)` query scope that limits results to the
 * records of the team(s) the user belongs to. Unrestricted users (owner, super
 * admin, or "View All Workspace Data") get the query untouched; scoped users
 * with no team get nothing (fail-closed).
 *
 * Models reach teams differently: Page/AdAccount have a direct `teams()`
 * relation, while Order reaches them through `page.teams`. Override
 * visibilityTeamRelation() where the path differs.
 */
trait ScopesToVisibleTeams
{
    protected function visibilityTeamRelation(): string
    {
        return 'teams';
    }

    public function scopeVisibleTo(Builder $query, User $user, Workspace $workspace): Builder
    {
        if (TeamVisibility::isUnrestricted($user, $workspace)) {
            return $query;
        }

        $teamIds = TeamVisibility::teamIdsFor($user, $workspace);

        if (empty($teamIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas(
            $this->visibilityTeamRelation(),
            fn (Builder $q) => $q->whereIn('teams.id', $teamIds),
        );
    }
}
