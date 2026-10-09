<?php

namespace Modules\TaskManagement\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Models\TaskList;
use Modules\TaskManagement\Policies\Concerns\ChecksSpaceRole;

class TaskListPolicy
{
    use ChecksSpaceRole;

    /**
     * Determine whether the user can view the list.
     */
    public function view(User $user, TaskList $list): Response
    {
        return $this->allowRoleAtLeast($user, $list->space, SpaceRole::Viewer);
    }

    /**
     * Determine whether the user can update the list.
     */
    public function update(User $user, TaskList $list): Response
    {
        return $this->allowRoleAtLeast($user, $list->space, SpaceRole::Admin);
    }

    /**
     * Determine whether the user can delete the list.
     */
    public function delete(User $user, TaskList $list): Response
    {
        return $this->allowRoleAtLeast($user, $list->space, SpaceRole::Admin);
    }

    /**
     * Determine whether the user can create and change tasks inside the list.
     */
    public function manageTasks(User $user, TaskList $list): Response
    {
        return $this->allowRoleAtLeast($user, $list->space, SpaceRole::Member);
    }
}
