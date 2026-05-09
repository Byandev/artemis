<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMetricSetting;
use Illuminate\Auth\Access\HandlesAuthorization;

class MetricSettingPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can manage workspace metric settings.
     */
    public function manage(User $user, Workspace $workspace): bool
    {
        // Allow if Global Admin
        // if ($user->is_admin)
        return true;

        // Otherwise, check workspace membership roles
        // return $workspace->members()
        //     ->where('user_id', $user->id)
        //     ->whereIn('role', ['admin', 'owner'])
        //     ->exists();
    }
}