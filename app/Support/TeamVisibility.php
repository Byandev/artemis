<?php

namespace App\Support;

use App\Enums\Permission;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Modules\MetaAds\Models\AdAccount;

/**
 * Central authority for team-level row visibility.
 *
 * A user is either "unrestricted" (sees everything in the workspace) or
 * "scoped" (sees only records belonging to the team(s) they're in). On top of
 * that, a user may pick a "viewing as team" in the switcher — a *view filter*
 * that narrows the page to one team for EVERYONE, including managers. Both the
 * Eloquent `scopeVisibleTo` (via App\Models\Concerns\ScopesToVisibleTeams) and
 * the metric/page-id resolution paths route through here so the rules live in
 * one place.
 */
class TeamVisibility
{
    public const SESSION_KEY = 'active_team_id';

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
     * Teams the user may pick in the "viewing as team" switcher: every team in
     * the workspace for unrestricted users, otherwise just their own teams.
     */
    public static function selectableTeams(User $user, Workspace $workspace): Collection
    {
        $query = Team::query()
            ->where('workspace_id', $workspace->id)
            ->orderBy('name');

        if (! self::isUnrestricted($user, $workspace)) {
            $query->whereHas('members', fn ($m) => $m->where('users.id', $user->id));
        }

        return $query->get(['id', 'name']);
    }

    /**
     * The currently selected "viewing as" team id, validated against the
     * workspace and the user's allowed teams. Null when none is chosen or the
     * stored value is no longer valid.
     *
     * The switcher drives changes via the `team_id` query param; the validated
     * choice is persisted to the session so it sticks across navigation that
     * omits the param. `team_id=all` (or empty) clears it.
     */
    public static function activeTeamId(User $user, Workspace $workspace): ?int
    {
        $fromQuery = request()->has('team_id');
        $candidate = $fromQuery ? request()->query('team_id') : session(self::SESSION_KEY);

        $resolved = self::resolveTeamId($candidate, $user, $workspace);

        if ($fromQuery) {
            session([self::SESSION_KEY => $resolved]);
        }

        return $resolved;
    }

    private static function resolveTeamId(mixed $candidate, User $user, Workspace $workspace): ?int
    {
        if (! $candidate || $candidate === 'all') {
            return null;
        }

        $teamId = (int) $candidate;

        $belongsToWorkspace = Team::where('id', $teamId)
            ->where('workspace_id', $workspace->id)
            ->exists();

        if (! $belongsToWorkspace) {
            return null;
        }

        if (self::isUnrestricted($user, $workspace)) {
            return $teamId;
        }

        return in_array($teamId, self::teamIdsFor($user, $workspace), true) ? $teamId : null;
    }

    /**
     * The team ids a query should be limited to, or null for "no restriction"
     * (unrestricted user with no active team selected).
     *
     * - Active team selected -> just that team, for EVERYONE (it's a view
     *   filter, so even managers narrow to it).
     * - Otherwise unrestricted -> null (see everything).
     * - Otherwise scoped -> the union of the user's teams (possibly empty,
     *   which fails closed).
     *
     * @return array<int, int>|null
     */
    public static function scopeTeamIds(User $user, Workspace $workspace): ?array
    {
        $activeTeamId = self::activeTeamId($user, $workspace);

        if ($activeTeamId !== null) {
            return [$activeTeamId];
        }

        if (self::isUnrestricted($user, $workspace)) {
            return null;
        }

        return self::teamIdsFor($user, $workspace);
    }

    /**
     * Whether any team restriction applies to this user's view right now.
     */
    public static function shouldScope(User $user, Workspace $workspace): bool
    {
        return self::scopeTeamIds($user, $workspace) !== null;
    }

    /**
     * Whether a user may *change* an ad account (budget/status/approve), as
     * opposed to merely viewing it. Requires a `manage`-tier team link, layered
     * on top of the role's verb permission. This is access-based and ignores the
     * "viewing as team" filter — the filter never grants or removes write access.
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
