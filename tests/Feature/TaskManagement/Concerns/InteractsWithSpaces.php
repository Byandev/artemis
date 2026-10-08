<?php

namespace Tests\Feature\TaskManagement\Concerns;

use App\Enums\Permission as PermissionEnum;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Modules\TaskManagement\Database\Factories\SpaceFactory;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Models\Folder;
use Modules\TaskManagement\Models\Label;
use Modules\TaskManagement\Models\Space;
use Modules\TaskManagement\Models\Task;
use Modules\TaskManagement\Models\TaskList;
use Modules\TaskManagement\Models\TaskStatus;

/**
 * The Matrix test helpers, plus the Artemis workspace every one of them runs in.
 *
 * Matrix had no workspaces: any account could own or join a space. To keep its
 * tests about what they were about -- space roles -- each test gets one
 * workspace with the module switched on, every space a factory makes is pinned
 * to it, and every user the test creates joins it holding "View Tasks". A test
 * that needs someone outside the workspace makes them with outsider().
 */
trait InteractsWithSpaces
{
    protected Workspace $taskWorkspace;

    protected Role $taskRole;

    protected function setUpInteractsWithSpaces(): void
    {
        $this->taskWorkspace = Workspace::factory()->create(['task_management_module_enabled' => true]);

        $this->taskRole = Role::create(['workspace_id' => $this->taskWorkspace->id, 'name' => 'Task user']);
        $permission = Permission::firstOrCreate(
            ['name' => PermissionEnum::ViewTasks->value],
            ['category' => 'Task Management'],
        );
        DB::table('role_permissions')->insert(['role_id' => $this->taskRole->id, 'permission_id' => $permission->id]);

        SpaceFactory::$workspaceId = $this->taskWorkspace->id;

        User::created(fn (User $user) => $this->joinTaskWorkspace($user));

        // Attachments go to the faked s3 disk whatever the local .env says.
        config(['filesystems.task_attachment_disk' => 's3']);
    }

    protected function tearDownInteractsWithSpaces(): void
    {
        SpaceFactory::$workspaceId = null;
    }

    /**
     * Add a user to the test workspace with a role holding "View Tasks".
     */
    protected function joinTaskWorkspace(User $user): void
    {
        DB::table('workspace_user')->insertOrIgnore([
            'workspace_id' => $this->taskWorkspace->id,
            'user_id' => $user->id,
            'role' => 'member',
            'role_id' => $this->taskRole->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * A user who does not belong to the test workspace.
     */
    protected function outsider(): User
    {
        return User::withoutEvents(fn () => User::factory()->create());
    }

    /**
     * The URL of a task-management API route inside the test workspace.
     */
    protected function tmRoute(string $name, mixed $parameters = []): string
    {
        $parameters = is_array($parameters) ? $parameters : [$parameters];

        return route('api.workspaces.task-management.'.$name, [$this->taskWorkspace, ...$parameters]);
    }

    /**
     * The URL of a task-management page inside the test workspace.
     */
    protected function tmPage(string $name, mixed $parameters = []): string
    {
        $parameters = is_array($parameters) ? $parameters : [$parameters];

        return route('workspaces.tasks.'.$name, [$this->taskWorkspace, ...$parameters]);
    }

    /**
     * Create a space owned by the given user, seeded with its default statuses.
     */
    protected function spaceOwnedBy(User $user): Space
    {
        return Space::factory()->withDefaultStatuses()->create(['owner_id' => $user->id]);
    }

    /**
     * Create a space the given user belongs to with the given role.
     */
    protected function spaceWhereUserIs(User $user, SpaceRole $role): Space
    {
        return Space::factory()
            ->withDefaultStatuses()
            ->withMember($user, $role)
            ->create();
    }

    /**
     * Create a folder inside the given space.
     */
    protected function folderIn(Space $space): Folder
    {
        return Folder::factory()->create(['space_id' => $space->id]);
    }

    /**
     * Create a list inside the given space, optionally inside a folder.
     */
    protected function listIn(Space $space, ?Folder $folder = null): TaskList
    {
        return TaskList::factory()->create([
            'space_id' => $space->id,
            'folder_id' => $folder?->id,
        ]);
    }

    /**
     * Create a task inside the given list using the space's default status.
     */
    protected function taskIn(TaskList $list, array $attributes = []): Task
    {
        return Task::factory()->create([
            'task_list_id' => $list->id,
            'task_status_id' => $this->defaultStatusOf($list->space),
            ...$attributes,
        ]);
    }

    /**
     * Create a label inside the given space.
     */
    protected function labelIn(Space $space): Label
    {
        return Label::factory()->create(['space_id' => $space->id]);
    }

    /**
     * Get the default status of the given space.
     */
    protected function defaultStatusOf(Space $space): TaskStatus
    {
        return $space->statuses()->where('is_default', true)->sole();
    }
}
