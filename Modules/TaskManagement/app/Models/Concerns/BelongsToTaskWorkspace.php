<?php

namespace Modules\TaskManagement\Models\Concerns;

/**
 * A task-management record that can say which Artemis workspace it belongs to.
 *
 * The API addresses records by id (`/tasks/{task}`) under a workspace prefix,
 * so the access middleware asks every bound record for its workspace and 404s
 * anything that belongs to another one.
 */
interface BelongsToTaskWorkspace
{
    public function taskWorkspaceId(): ?int;
}
