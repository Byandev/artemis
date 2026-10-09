<?php

namespace Modules\TaskManagement\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Models\Task;
use Modules\TaskManagement\Policies\Concerns\ChecksSpaceRole;

class TaskPolicy
{
    use ChecksSpaceRole;

    /**
     * Determine whether the user can view the task.
     */
    public function view(User $user, Task $task): Response
    {
        return $this->allowRoleAtLeast($user, $task->list->space, SpaceRole::Viewer);
    }

    /**
     * Determine whether the user can update the task.
     */
    public function update(User $user, Task $task): Response
    {
        return $this->allowRoleAtLeast($user, $task->list->space, SpaceRole::Member);
    }

    /**
     * Determine whether the user can delete the task.
     */
    public function delete(User $user, Task $task): Response
    {
        return $this->allowRoleAtLeast($user, $task->list->space, SpaceRole::Member);
    }
}
