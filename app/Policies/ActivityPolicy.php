<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;
use App\Models\Workspace;

class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        // Global activity view - only platform super-admins
        return $user->isSuperAdmin();
    }

    public function viewAnyForWorkspace(User $user, Workspace $workspace): bool
    {
        // Workspace scoped view - workspace admins or owners
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdminOf($workspace) || $user->ownsWorkspace($workspace);
    }
}
