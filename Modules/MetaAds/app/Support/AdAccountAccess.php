<?php

namespace Modules\MetaAds\Support;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for per-user, per-ad-account access within a workspace.
 *
 * Access is layered *on top of* role permissions: the role decides whether a
 * member can touch ad accounts / optimization rules at all, while the grants in
 * `meta_ads_account_access` narrow *which* accounts those abilities apply to.
 *
 * A member with no grants is unrestricted (preserves the prior all-or-nothing
 * behavior). Owners, workspace admins, and super-admins are always unrestricted.
 */
class AdAccountAccess
{
    /**
     * Account ids (as strings) the user may VIEW, or null when unrestricted.
     *
     * @return list<string>|null
     */
    public static function viewableIds(User $user, Workspace $workspace): ?array
    {
        return self::scopedIds($user, $workspace, ['view', 'manage']);
    }

    /**
     * Account ids (as strings) the user may MANAGE, or null when unrestricted.
     *
     * @return list<string>|null
     */
    public static function manageableIds(User $user, Workspace $workspace): ?array
    {
        return self::scopedIds($user, $workspace, ['manage']);
    }

    public static function canView(User $user, Workspace $workspace, int|string $accountId): bool
    {
        $ids = self::viewableIds($user, $workspace);

        return $ids === null || in_array((string) $accountId, $ids, true);
    }

    public static function canManage(User $user, Workspace $workspace, int|string $accountId): bool
    {
        $ids = self::manageableIds($user, $workspace);

        return $ids === null || in_array((string) $accountId, $ids, true);
    }

    /**
     * True when the user can manage every one of the given account ids.
     *
     * @param  iterable<int|string>  $accountIds
     */
    public static function canManageAll(User $user, Workspace $workspace, iterable $accountIds): bool
    {
        $ids = self::manageableIds($user, $workspace);

        if ($ids === null) {
            return true;
        }

        foreach ($accountIds as $accountId) {
            if (! in_array((string) $accountId, $ids, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $levels
     * @return list<string>|null
     */
    private static function scopedIds(User $user, Workspace $workspace, array $levels): ?array
    {
        if (self::isUnrestricted($user, $workspace)) {
            return null;
        }

        return DB::table('meta_ads_account_access')
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->whereIn('access_level', $levels)
            ->pluck('meta_ads_account_id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    private static function isUnrestricted(User $user, Workspace $workspace): bool
    {
        if ($user->isSuperAdmin() || $user->ownsWorkspace($workspace) || $user->isAdminOf($workspace)) {
            return true;
        }

        // No grants → unrestricted (the member keeps full access, as before).
        return ! DB::table('meta_ads_account_access')
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->exists();
    }
}
