<?php

namespace Modules\TaskManagement\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Models\Space;
use Modules\TaskManagement\Policies\Concerns\ChecksSpaceRole;

class SpacePolicy
{
    use ChecksSpaceRole;

    /**
     * Determine whether the user can list their spaces.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the space.
     */
    public function view(User $user, Space $space): Response
    {
        return $this->allowRoleAtLeast($user, $space, SpaceRole::Viewer);
    }

    /**
     * Determine whether the user can create a space.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the space itself.
     */
    public function update(User $user, Space $space): Response
    {
        return $this->allowRoleAtLeast($user, $space, SpaceRole::Admin);
    }

    /**
     * Determine whether the user can delete the space.
     */
    public function delete(User $user, Space $space): Response
    {
        return $this->allowRoleAtLeast($user, $space, SpaceRole::Owner);
    }

    /**
     * Determine whether the user can manage the folders, lists, statuses and labels of the space.
     */
    public function manageStructure(User $user, Space $space): Response
    {
        return $this->allowRoleAtLeast($user, $space, SpaceRole::Admin);
    }

    /**
     * Determine whether the user can add, re-role and remove the members of the space.
     */
    public function manageMembers(User $user, Space $space): Response
    {
        return $this->allowRoleAtLeast($user, $space, SpaceRole::Admin);
    }

    /**
     * Determine whether the user can create and change tasks inside the space.
     */
    public function manageTasks(User $user, Space $space): Response
    {
        return $this->allowRoleAtLeast($user, $space, SpaceRole::Member);
    }
}
