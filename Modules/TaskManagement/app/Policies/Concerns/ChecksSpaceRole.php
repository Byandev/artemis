<?php

namespace Modules\TaskManagement\Policies\Concerns;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Models\Space;

trait ChecksSpaceRole
{
    /**
     * Allow the action when the user holds at least the given role in the space.
     *
     * A user outside the space is denied as not found so the API never
     * confirms that another space's record exists.
     */
    protected function allowRoleAtLeast(User $user, Space $space, SpaceRole $minimum): Response
    {
        $role = $space->roleFor($user);

        if ($role === null) {
            return Response::denyAsNotFound();
        }

        return $role->atLeast($minimum)
            ? Response::allow()
            : Response::denyWithStatus(403, __('Your role in this space does not allow this action.'));
    }
}
