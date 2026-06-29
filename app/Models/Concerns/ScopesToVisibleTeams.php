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
 * Models reach teams differently: Shop/AdAccount have a direct `teams()`
 * relation, a Page reaches them through `shop.teams`, and a pancake Order
 * through its own `shop.teams` (shop_id is denormalised onto the order).
 * Override visibilityTeamRelation() where the path differs.
 */
trait ScopesToVisibleTeams
{
    protected function visibilityTeamRelation(): string
    {
        return 'teams';
    }

    public function scopeVisibleTo(Builder $query, User $user, Workspace $workspace): Builder
    {
        $teamIds = TeamVisibility::scopeTeamIds($user, $workspace);

        // null -> unrestricted with no "viewing as team" selected: see everything.
        if ($teamIds === null) {
            return $query;
        }

        // Scoped user with no team -> nothing (fail-closed).
        if (empty($teamIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas(
            $this->visibilityTeamRelation(),
            fn (Builder $q) => $q->whereIn('teams.id', $teamIds),
        );
    }
}
