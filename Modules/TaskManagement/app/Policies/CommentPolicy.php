<?php

namespace Modules\TaskManagement\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Models\Comment;
use Modules\TaskManagement\Policies\Concerns\ChecksSpaceRole;

/**
 * Reading and posting are authorized against the task itself, through
 * TaskPolicy::view -- taking part in the discussion is not editing the work, so
 * a viewer who can see a task can read and add to its comments. This policy
 * covers only the two abilities that belong to the comment rather than the task.
 */
class CommentPolicy
{
    use ChecksSpaceRole;

    /**
     * Determine whether the user can edit the comment.
     */
    public function update(User $user, Comment $comment): Response
    {
        return $this->allowAuthorOnly($user, $comment);
    }

    /**
     * Determine whether the user can delete the comment.
     */
    public function delete(User $user, Comment $comment): Response
    {
        return $this->allowAuthorOnly($user, $comment);
    }

    /**
     * Allow only the comment's author, and only if they can still see the task.
     *
     * The two denials are deliberately different, per the split this project
     * keeps: someone outside the space is told the comment does not exist, so
     * nothing confirms another tenant's record; a member touching someone
     * else's comment is told plainly that it is not theirs, because they can
     * already see it.
     */
    private function allowAuthorOnly(User $user, Comment $comment): Response
    {
        $visible = $this->allowRoleAtLeast($user, $comment->task->list->space, SpaceRole::Viewer);

        if ($visible->denied()) {
            return $visible;
        }

        return $comment->user_id === $user->id
            ? Response::allow()
            : Response::denyWithStatus(403, __('You can only change your own comments.'));
    }
}
