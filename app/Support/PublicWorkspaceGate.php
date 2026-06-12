<?php

namespace App\Support;

use App\Enums\Permission;
use App\Models\Workspace;
use Illuminate\Http\Request;

/**
 * Shared password gate for a workspace's public pages (RMO management and the
 * leaderboard). When a workspace has a public password set, every visitor —
 * including signed-in users who hold the relevant role permission — must enter
 * it once per browser session before the page reveals its data.
 */
class PublicWorkspaceGate
{
    public static function sessionKey(Workspace $workspace): string
    {
        return 'public_verified_'.$workspace->id;
    }

    /**
     * Unlocked only when a public password is configured AND the current
     * browser session has already entered it correctly. When no password is
     * set the public pages stay closed — they are never open by default.
     * Login and role permissions never bypass the gate.
     */
    public static function isUnlocked(Request $request, Workspace $workspace, ?Permission $permission = null): bool
    {
        if (! $workspace->public_password_set) {
            return false;
        }

        return (bool) $request->session()->get(self::sessionKey($workspace));
    }

    /** Check the password and, on success, mark the session as verified. */
    public static function verify(Request $request, Workspace $workspace, string $password): bool
    {
        if (! $workspace->checkPublicPassword($password)) {
            return false;
        }

        $request->session()->put(self::sessionKey($workspace), true);

        return true;
    }
}
