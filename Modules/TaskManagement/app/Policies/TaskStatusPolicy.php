<?php

namespace Modules\TaskManagement\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Models\TaskStatus;
use Modules\TaskManagement\Policies\Concerns\ChecksSpaceRole;

class TaskStatusPolicy
{
    use ChecksSpaceRole;

    /**
     * Determine whether the user can view the status.
     */
    public function view(User $user, TaskStatus $status): Response
    {
        return $this->allowRoleAtLeast($user, $status->space, SpaceRole::Viewer);
    }

    /**
     * Determine whether the user can update the status.
     */
    public function update(User $user, TaskStatus $status): Response
    {
        return $this->allowRoleAtLeast($user, $status->space, SpaceRole::Admin);
    }

    /**
     * Determine whether the user can delete the status.
     */
    public function delete(User $user, TaskStatus $status): Response
    {
        return $this->allowRoleAtLeast($user, $status->space, SpaceRole::Admin);
    }
}
