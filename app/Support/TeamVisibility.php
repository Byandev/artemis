<?php

namespace App\Support;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Workspace;
use Modules\MetaAds\Models\AdAccount;

/**
 * Central authority for team-level row visibility.
 *
 * A user is either "unrestricted" (sees everything in the workspace) or
 * "scoped" (sees only records belonging to the team(s) they're in). Both the
 * Eloquent `scopeVisibleTo` (via App\Models\Concerns\ScopesToVisibleTeams) and
 * the metric/page-id resolution paths route through here so the rules live in
 * one place.
 */
class TeamVisibility
{
    /**
     * Unrestricted users bypass all team scoping. hasPermission() already
     * short-circuits true for super admins and workspace owners, so this single
     * check covers them too.
     */
    public static function isUnrestricted(User $user, Workspace $workspace): bool
    {
        return $user->hasPermission(Permission::ViewAllWorkspaceData, $workspace);
    }

    /**
     * The team ids a user belongs to within a given workspace.
     *
     * @return array<int, int>
     */
    public static function teamIdsFor(User $user, Workspace $workspace): array
    {
        return $user->teams()
            ->where('teams.workspace_id', $workspace->id)
            ->pluck('teams.id')
            ->all();
    }

    /**
     * Whether a user may *change* an ad account (budget/status/approve), as
     * opposed to merely viewing it. Requires a `manage`-tier team link. This is
     * layered on top of the role's verb permission, not a replacement for it.
     */
    public static function canManageAdAccount(User $user, AdAccount $account, Workspace $workspace): bool
    {
        if (self::isUnrestricted($user, $workspace)) {
            return true;
        }

        $teamIds = self::teamIdsFor($user, $workspace);

        if (empty($teamIds)) {
            return false;
        }

        return $account->teams()
            ->whereIn('teams.id', $teamIds)
            ->wherePivot('access_level', 'manage')
            ->exists();
    }
}
