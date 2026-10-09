<?php

namespace Tests\Feature\TaskManagement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Enums\TaskPriority;
use Modules\TaskManagement\Enums\TaskStatusType;
use Modules\TaskManagement\Models\Label;
use Modules\TaskManagement\Models\Task;
use Modules\TaskManagement\Models\TaskList;
use Modules\TaskManagement\Models\TaskStatus;
use Tests\Feature\TaskManagement\Concerns\InteractsWithSpaces;
use Tests\TestCase;

class TaskControllerTest extends TestCase
{
    use InteractsWithSpaces, RefreshDatabase;

    public function test_store_creates_a_task_in_the_list_and_returns_201(): void
    {
        $user = User::factory()->create();
        $list = $this->listIn($this->spaceOwnedBy($user));

        $response = $this->actingAs($user)->postJson($this->tmRoute('lists.tasks.store', $list), [
            'name' => 'Ship the API',
            'description' => 'Wire OpenClaw to the module.',
            'priority' => TaskPriority::High->value,
            'due_at' => '2026-10-01T09:00:00+00:00',
            'estimate_minutes' => 90,
            'metadata' => ['sprint' => 'alpha', 'points' => 5],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Ship the API')
            ->assertJsonPath('data.priority', TaskPriority::High->value)
            ->assertJsonPath('data.list_id', $list->id)
            ->assertJsonPath('data.metadata.sprint', 'alpha');

        $task = Task::sole();
        $this->assertSame($user->id, $task->created_by);
        $this->assertSame(90, $task->estimate_minutes);
    }

    public function test_store_falls_back_to_the_default_status_of_the_space(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $list = $this->listIn($space);

        $this->actingAs($user)
            ->postJson($this->tmRoute('lists.tasks.store', $list), ['name' => 'Ship the API'])
            ->assertCreated()
            ->assertJsonPath('data.status_id', $this->defaultStatusOf($space)->id);
    }

    public function test_store_appends_the_task_after_the_existing_ones_in_the_list(): void
    {
        $user = User::factory()->create();
        $list = $this->listIn($this->spaceOwnedBy($user));
        $this->taskIn($list, ['position' => 4]);

        $this->actingAs($user)
            ->postJson($this->tmRoute('lists.tasks.store', $list), ['name' => 'Next'])
            ->assertCreated()
            ->assertJsonPath('data.position', 5);
    }

    public function test_store_attaches_assignees_and_labels(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $teammate = User::factory()->create();
        $space->members()->attach($teammate, ['role' => SpaceRole::Member->value]);
        $label = $this->labelIn($space);
        $list = $this->listIn($space);

        $response = $this->actingAs($user)->postJson($this->tmRoute('lists.tasks.store', $list), [
            'name' => 'Ship the API',
            'assignee_ids' => [$teammate->id],
            'label_ids' => [$label->id],
        ]);

        $response->assertCreated();

        $task = Task::sole();
        $this->assertSame([$teammate->id], $task->assignees()->pluck('users.id')->all());
        $this->assertSame([$label->id], $task->labels()->pluck('task_labels.id')->all());
    }

    public function test_store_returns_422_when_an_assignee_is_not_a_member_of_the_space(): void
    {
        $user = User::factory()->create();
        $list = $this->listIn($this->spaceOwnedBy($user));

        $this->actingAs($user)
            ->postJson($this->tmRoute('lists.tasks.store', $list), [
                'name' => 'Ship the API',
                'assignee_ids' => [User::factory()->create()->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assignee_ids.0');
    }

    public function test_store_returns_422_when_the_label_belongs_to_another_space(): void
    {
        $user = User::factory()->create();
        $list = $this->listIn($this->spaceOwnedBy($user));

        $this->actingAs($user)
            ->postJson($this->tmRoute('lists.tasks.store', $list), [
                'name' => 'Ship the API',
                'label_ids' => [Label::factory()->create()->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('label_ids.0');
    }

    public function test_store_returns_422_when_the_status_belongs_to_another_space(): void
    {
        $user = User::factory()->create();
        $list = $this->listIn($this->spaceOwnedBy($user));

        $this->actingAs($user)
            ->postJson($this->tmRoute('lists.tasks.store', $list), [
                'name' => 'Ship the API',
                'status_id' => TaskStatus::factory()->create()->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status_id');
    }

    public function test_store_returns_422_when_the_name_is_missing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson($this->tmRoute('lists.tasks.store', $this->listIn($this->spaceOwnedBy($user))), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_store_returns_403_for_a_viewer(): void
    {
        $user = User::factory()->create();
        $list = $this->listIn($this->spaceWhereUserIs($user, SpaceRole::Viewer));

        $this->actingAs($user)
            ->postJson($this->tmRoute('lists.tasks.store', $list), ['name' => 'Ship the API'])
            ->assertForbidden();

        $this->assertDatabaseEmpty('tasks');
    }

    public function test_a_member_can_create_a_task(): void
    {
        $user = User::factory()->create();
        $list = $this->listIn($this->spaceWhereUserIs($user, SpaceRole::Member));

        $this->actingAs($user)
            ->postJson($this->tmRoute('lists.tasks.store', $list), ['name' => 'Ship the API'])
            ->assertCreated();
    }

    public function test_store_returns_404_for_a_list_in_another_space(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson($this->tmRoute('lists.tasks.store', TaskList::factory()->create()), ['name' => 'x'])
            ->assertNotFound();
    }

    public function test_show_returns_the_task_with_its_relationships(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $list = $this->listIn($space);
        $task = $this->taskIn($list);
        $label = $this->labelIn($space);
        $task->labels()->attach($label);
        $task->assignees()->attach($user);

        $this->actingAs($user)
            ->getJson($this->tmRoute('tasks.show', $task))
            ->assertOk()
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonPath('data.labels.0.id', $label->id)
            ->assertJsonPath('data.assignees.0.id', $user->id)
            ->assertJsonPath('data.status.id', $task->task_status_id)
            ->assertJsonPath('data.list.id', $list->id);
    }

    public function test_show_returns_404_for_a_task_in_another_space(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson($this->tmRoute('tasks.show', Task::factory()->create()))
            ->assertNotFound();
    }

    public function test_show_includes_the_subtask_tree(): void
    {
        $user = User::factory()->create();
        $list = $this->listIn($this->spaceOwnedBy($user));
        $parent = $this->taskIn($list);
        $child = $this->taskIn($list, ['parent_id' => $parent->id, 'name' => 'A subtask']);

        $this->actingAs($user)
            ->getJson($this->tmRoute('tasks.show', $parent))
            ->assertOk()
            ->assertJsonPath('data.subtasks.0.id', $child->id)
            ->assertJsonPath('data.subtasks.0.name', 'A subtask')
            ->assertJsonPath('data.subtasks.0.status.id', $child->task_status_id);
    }

    public function test_update_changes_the_editable_attributes(): void
    {
        $user = User::factory()->create();
        $task = $this->taskIn($this->listIn($this->spaceOwnedBy($user)));

        $this->actingAs($user)
            ->patchJson($this->tmRoute('tasks.update', $task), [
                'name' => 'Renamed',
                'priority' => TaskPriority::Urgent->value,
                'metadata' => ['sprint' => 'beta'],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.priority', TaskPriority::Urgent->value)
            ->assertJsonPath('data.metadata.sprint', 'beta');
    }

    public function test_update_clears_the_priority_due_date_and_assignees(): void
    {
        $user = User::factory()->create();
        $task = $this->taskIn($this->listIn($this->spaceOwnedBy($user)), [
            'priority' => TaskPriority::High->value,
            'due_at' => now()->addDay(),
        ]);
        $task->assignees()->attach($user);

        $this->actingAs($user)
            ->patchJson($this->tmRoute('tasks.update', $task), [
                'priority' => null,
                'due_at' => null,
                'assignee_ids' => [],
            ])
            ->assertOk()
            ->assertJsonPath('data.priority', null)
            ->assertJsonPath('data.due_at', null);

        $this->assertSame([], $task->fresh()->assignees()->pluck('users.id')->all());
    }

    public function test_moving_a_task_to_a_done_status_stamps_completed_at(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $task = $this->taskIn($this->listIn($space));
        $done = $space->statuses()->where('slug', 'done')->sole();

        $this->actingAs($user)
            ->patchJson($this->tmRoute('tasks.update', $task), ['status_id' => $done->id])
            ->assertOk()
            ->assertJsonPath('data.completed', true);

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_moving_a_task_back_to_an_open_status_clears_completed_at(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $done = $space->statuses()->where('slug', 'done')->sole();
        $task = $this->taskIn($this->listIn($space), [
            'task_status_id' => $done->id,
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->patchJson($this->tmRoute('tasks.update', $task), [
                'status_id' => $this->defaultStatusOf($space)->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.completed', false);

        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_update_moves_the_task_to_another_list_in_the_same_space(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $task = $this->taskIn($this->listIn($space));
        $target = $this->listIn($space);

        $this->actingAs($user)
            ->patchJson($this->tmRoute('tasks.update', $task), ['list_id' => $target->id])
            ->assertOk()
            ->assertJsonPath('data.list_id', $target->id);

        $this->assertSame($target->id, $task->fresh()->task_list_id);
    }

    public function test_update_returns_422_when_moving_the_task_into_a_foreign_list(): void
    {
        $user = User::factory()->create();
        $task = $this->taskIn($this->listIn($this->spaceOwnedBy($user)));

        $this->actingAs($user)
            ->patchJson($this->tmRoute('tasks.update', $task), [
                'list_id' => TaskList::factory()->create()->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('list_id');
    }

    public function test_update_replaces_the_assignees(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $teammate = User::factory()->create();
        $space->members()->attach($teammate, ['role' => SpaceRole::Member->value]);
        $task = $this->taskIn($this->listIn($space));
        $task->assignees()->attach($user);

        $this->actingAs($user)
            ->patchJson($this->tmRoute('tasks.update', $task), ['assignee_ids' => [$teammate->id]])
            ->assertOk();

        $this->assertSame([$teammate->id], $task->fresh()->assignees()->pluck('users.id')->all());
    }

    public function test_update_returns_403_for_a_viewer(): void
    {
        $user = User::factory()->create();
        $task = $this->taskIn($this->listIn($this->spaceWhereUserIs($user, SpaceRole::Viewer)));

        $this->actingAs($user)
            ->patchJson($this->tmRoute('tasks.update', $task), ['name' => 'Renamed'])
            ->assertForbidden();
    }

    public function test_archiving_a_task_keeps_it_out_of_the_default_index(): void
    {
        $user = User::factory()->create();
        $task = $this->taskIn($this->listIn($this->spaceOwnedBy($user)));

        $this->actingAs($user)
            ->patchJson($this->tmRoute('tasks.update', $task), ['archived' => true])
            ->assertOk()
            ->assertJsonPath('data.archived', true);

        $this->actingAs($user)
            ->getJson($this->tmRoute('tasks.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_subtask_can_be_created_under_a_parent_task(): void
    {
        $user = User::factory()->create();
        $list = $this->listIn($this->spaceOwnedBy($user));
        $parent = $this->taskIn($list);

        $this->actingAs($user)
            ->postJson($this->tmRoute('lists.tasks.store', $list), [
                'name' => 'Subtask',
                'parent_id' => $parent->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.parent_id', $parent->id);
    }

    public function test_store_returns_422_when_the_parent_task_is_in_another_list(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $list = $this->listIn($space);
        $otherParent = $this->taskIn($this->listIn($space));

        $this->actingAs($user)
            ->postJson($this->tmRoute('lists.tasks.store', $list), [
                'name' => 'Subtask',
                'parent_id' => $otherParent->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_destroy_deletes_the_task_and_its_subtasks(): void
    {
        $user = User::factory()->create();
        $list = $this->listIn($this->spaceOwnedBy($user));
        $parent = $this->taskIn($list);
        $this->taskIn($list, ['parent_id' => $parent->id]);

        $this->actingAs($user)
            ->deleteJson($this->tmRoute('tasks.destroy', $parent))
            ->assertNoContent();

        $this->assertDatabaseEmpty('tasks');
    }

    public function test_destroy_returns_403_for_a_viewer(): void
    {
        $user = User::factory()->create();
        $task = $this->taskIn($this->listIn($this->spaceWhereUserIs($user, SpaceRole::Viewer)));

        $this->actingAs($user)
            ->deleteJson($this->tmRoute('tasks.destroy', $task))
            ->assertForbidden();

        $this->assertNotNull($task->fresh());
    }

    public function test_status_type_is_exposed_so_clients_can_group_by_stage(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $task = $this->taskIn($this->listIn($space));

        $this->actingAs($user)
            ->getJson($this->tmRoute('tasks.show', $task))
            ->assertOk()
            ->assertJsonPath('data.status.type', TaskStatusType::NotStarted->value);
    }
}
