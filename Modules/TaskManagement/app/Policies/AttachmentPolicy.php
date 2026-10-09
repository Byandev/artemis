<?php

namespace Modules\TaskManagement\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Models\Attachment;
use Modules\TaskManagement\Models\Task;
use Modules\TaskManagement\Policies\Concerns\ChecksSpaceRole;

/**
 * Attaching a file is authorized against the task itself, through
 * TaskPolicy::update -- adding a document changes the work, so it takes a
 * member, unlike commenting, which a viewer may do. This policy covers the two
 * abilities that belong to the file rather than the task.
 */
class AttachmentPolicy
{
    use ChecksSpaceRole;

    /**
     * Determine whether the user can download the file.
     */
    public function view(User $user, Attachment $attachment): Response
    {
        $task = $this->taskOf($attachment);

        if ($task === null) {
            return Response::denyAsNotFound();
        }

        return $this->allowRoleAtLeast($user, $task->list->space, SpaceRole::Viewer);
    }

    /**
     * Determine whether the user can delete the file.
     *
     * The uploader can take their own file back down, and an admin can take
     * anyone's: a file is a shared artefact of the task, so a wrong or stale one
     * must still be removable once the person who added it has left the space.
     * Everyone else is told plainly that it is not theirs, because they can
     * already see it -- the not-found answer is reserved for someone outside the
     * space, so nothing confirms another tenant's record.
     */
    public function delete(User $user, Attachment $attachment): Response
    {
        $task = $this->taskOf($attachment);

        if ($task === null) {
            return Response::denyAsNotFound();
        }

        $space = $task->list->space;

        $allowed = $this->allowRoleAtLeast($user, $space, SpaceRole::Member);

        if ($allowed->denied()) {
            return $allowed;
        }

        $role = $space->roleFor($user);

        if ($attachment->uploaded_by === $user->id || $role?->atLeast(SpaceRole::Admin)) {
            return Response::allow();
        }

        return Response::denyWithStatus(403, __('Only the person who attached this file, or a space admin, can delete it.'));
    }

    /**
     * Get the task a file hangs off, or null when it hangs off anything else.
     *
     * Every upload is an Attachment, so a media row for some later feature would
     * reach this policy too. Anything that is not a task file is denied as not
     * found rather than guessed at.
     */
    private function taskOf(Attachment $attachment): ?Task
    {
        $model = $attachment->model;

        return $model instanceof Task ? $model : null;
    }
}
