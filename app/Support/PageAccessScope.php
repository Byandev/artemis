<?php

namespace App\Support;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Resolves how much page-scoped data a user is allowed to see in a workspace.
 *
 * A user with the "Access All Pages" permission (or a workspace owner / super
 * admin) sees everything. Otherwise they are limited to pages owned by members
 * of the team(s) they belong to — the same owner_id → team relationship the
 * `team_ids` metric filter already uses.
 */
class PageAccessScope
{
    /**
     * The page-owner IDs the user is limited to, or null when unrestricted.
     *
     * When restricted the user always at least sees their own pages, plus the
     * pages of everyone who shares a team with them.
     *
     * @return array<int>|null
     */
    public static function ownerIdsFor(?User $user, Workspace $workspace): ?array
    {
        if (! $user || $user->canAccessAllPages($workspace)) {
            return null;
        }

        $teamIds = DB::table('team_user')
            ->join('teams', 'teams.id', '=', 'team_user.team_id')
            ->where('team_user.user_id', $user->id)
            ->where('teams.workspace_id', $workspace->id)
            ->pluck('team_user.team_id');

        return DB::table('team_user')
            ->whereIn('team_id', $teamIds)
            ->pluck('user_id')
            ->push($user->id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Merge the owner-id restriction into a metrics filter array. No-op when the
     * user has unrestricted access, so existing filters pass through untouched.
     *
     * @param  array<string, mixed>  $filter
     * @return array<string, mixed>
     */
    public static function applyToFilter(array $filter, ?User $user, Workspace $workspace): array
    {
        $ownerIds = self::ownerIdsFor($user, $workspace);

        if ($ownerIds !== null) {
            $filter['restrict_owner_ids'] = $ownerIds;
        }

        return $filter;
    }
}
