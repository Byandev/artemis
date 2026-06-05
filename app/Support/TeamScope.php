<?php

namespace App\Support;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the data a user is allowed to see within a workspace.
 *
 * Users with the "View All Workspace Data" permission (and workspace
 * owners / super-admins, who pass every permission check) are unrestricted.
 * Everyone else is limited to their team(s): the data of pages owned by their
 * teammates. A user who belongs to no team falls back to only their own pages.
 *
 * All workspace data ultimately hangs off pages.owner_id, so restricting the
 * set of allowed owner IDs is sufficient to scope every metric and list.
 */
class TeamScope
{
    /** True when the user may only see their own team's data. */
    public static function restricted(User $user, Workspace $workspace): bool
    {
        return ! $user->hasPermission(Permission::ViewAllWorkspaceData, $workspace);
    }

    /**
     * Page-owner IDs the user is allowed to see, or null when unrestricted.
     *
     * @return list<int>|null
     */
    public static function allowedOwnerIds(User $user, Workspace $workspace): ?array
    {
        if (! self::restricted($user, $workspace)) {
            return null;
        }

        $teamIds = self::teamIds($user, $workspace);

        // No team → only the user's own pages.
        if (empty($teamIds)) {
            return [$user->id];
        }

        return DB::table('team_user')
            ->whereIn('team_id', $teamIds)
            ->pluck('user_id')
            ->push($user->id)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Team IDs the user belongs to within the workspace, or null when unrestricted.
     *
     * @return list<int>|null
     */
    public static function allowedTeamIds(User $user, Workspace $workspace): ?array
    {
        if (! self::restricted($user, $workspace)) {
            return null;
        }

        return self::teamIds($user, $workspace);
    }

    /**
     * @return list<int>
     */
    private static function teamIds(User $user, Workspace $workspace): array
    {
        return DB::table('team_user')
            ->join('teams', 'teams.id', '=', 'team_user.team_id')
            ->where('team_user.user_id', $user->id)
            ->where('teams.workspace_id', $workspace->id)
            ->pluck('team_user.team_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
