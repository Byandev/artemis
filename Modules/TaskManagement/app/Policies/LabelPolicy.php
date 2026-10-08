<?php

namespace Modules\TaskManagement\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Models\Label;
use Modules\TaskManagement\Policies\Concerns\ChecksSpaceRole;

class LabelPolicy
{
    use ChecksSpaceRole;

    /**
     * Determine whether the user can view the label.
     */
    public function view(User $user, Label $label): Response
    {
        return $this->allowRoleAtLeast($user, $label->space, SpaceRole::Viewer);
    }

    /**
     * Determine whether the user can update the label.
     */
    public function update(User $user, Label $label): Response
    {
        return $this->allowRoleAtLeast($user, $label->space, SpaceRole::Admin);
    }

    /**
     * Determine whether the user can delete the label.
     */
    public function delete(User $user, Label $label): Response
    {
        return $this->allowRoleAtLeast($user, $label->space, SpaceRole::Admin);
    }
}
