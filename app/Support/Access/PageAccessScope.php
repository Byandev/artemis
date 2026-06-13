<?php

namespace App\Support\Access;

use App\Models\PageAccessGrant;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;

/**
 * Resolves which pages a user may see in a workspace.
 *
 * Grants attach to a User or a Team; a user's effective access is the UNION of
 * their own grants and the grants of every team they belong to. A user with no
 * grants (and in no granted team) is UNRESTRICTED — represented as null so call
 * sites can skip filtering entirely. Workspace owners and super-admins are
 * always unrestricted.
 */
class PageAccessScope
{
    /** @var array<string, array<int, int>|null> per-request memo keyed by "userId:workspaceId" */
    private static array $cache = [];

    /**
     * Page ids the user may see, or null when unrestricted (= all pages).
     *
     * @return array<int, int>|null
     */
    public static function allowedPageIds(User $user, Workspace $workspace): ?array
    {
        $key = $user->id.':'.$workspace->id;

        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        return self::$cache[$key] = self::resolve($user, $workspace);
    }

    /**
     * @return array<int, int>|null
     */
    private static function resolve(User $user, Workspace $workspace): ?array
    {
        if ($user->isSuperAdmin() || $user->ownsWorkspace($workspace)) {
            return null;
        }

        $teamIds = $user->teams()
            ->where('teams.workspace_id', $workspace->id)
            ->pluck('teams.id')
            ->all();

        $pageIds = PageAccessGrant::query()
            ->where('workspace_id', $workspace->id)
            ->where(function ($query) use ($user, $teamIds) {
                $query->where(function ($q) use ($user) {
                    $q->where('grantee_type', $user->getMorphClass())
                        ->where('grantee_id', $user->id);
                });

                if ($teamIds !== []) {
                    $query->orWhere(function ($q) use ($teamIds) {
                        $q->where('grantee_type', (new Team)->getMorphClass())
                            ->whereIn('grantee_id', $teamIds);
                    });
                }
            })
            ->pluck('page_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        // No grants anywhere → unrestricted.
        return $pageIds === [] ? null : $pageIds;
    }

    /**
     * Clear the per-request memo (useful in tests after mutating grants).
     */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
