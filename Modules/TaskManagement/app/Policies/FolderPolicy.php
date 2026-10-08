<?php

namespace Modules\TaskManagement\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Models\Folder;
use Modules\TaskManagement\Policies\Concerns\ChecksSpaceRole;

class FolderPolicy
{
    use ChecksSpaceRole;

    /**
     * Determine whether the user can view the folder.
     */
    public function view(User $user, Folder $folder): Response
    {
        return $this->allowRoleAtLeast($user, $folder->space, SpaceRole::Viewer);
    }

    /**
     * Determine whether the user can update the folder.
     */
    public function update(User $user, Folder $folder): Response
    {
        return $this->allowRoleAtLeast($user, $folder->space, SpaceRole::Admin);
    }

    /**
     * Determine whether the user can delete the folder.
     */
    public function delete(User $user, Folder $folder): Response
    {
        return $this->allowRoleAtLeast($user, $folder->space, SpaceRole::Admin);
    }
}
