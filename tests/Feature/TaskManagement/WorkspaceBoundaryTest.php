<?php

namespace Tests\Feature\TaskManagement;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Models\Folder;
use Modules\TaskManagement\Models\Space;
use Tests\Feature\TaskManagement\Concerns\InteractsWithSpaces;
use Tests\TestCase;

/**
 * What Artemis adds on top of Matrix's space roles: the module toggle, the
 * workspace membership and "View Tasks" gate, and records never crossing from
 * one workspace into another.
 */
class WorkspaceBoundaryTest extends TestCase
{
    use InteractsWithSpaces, RefreshDatabase;

    public function test_the_api_answers_404_when_the_module_is_off(): void
    {
        $user = User::factory()->create();
        $this->taskWorkspace->update(['task_management_module_enabled' => false]);

        $this->actingAs($user)->getJson($this->tmRoute('spaces.index'))->assertNotFound();
        $this->actingAs($user)->get($this->tmPage('index'))->assertNotFound();
    }

    public function test_the_api_requires_a_signed_in_user(): void
    {
        $this->getJson($this->tmRoute('spaces.index'))->assertUnauthorized();
    }

    public function test_someone_outside_the_workspace_is_refused(): void
    {
        $this->actingAs($this->outsider())
            ->getJson($this->tmRoute('spaces.index'))
            ->assertForbidden();
    }

    public function test_a_workspace_member_without_view_tasks_is_refused(): void
    {
        $member = $this->outsider();
        $this->taskWorkspace->users()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)->getJson($this->tmRoute('spaces.index'))->assertForbidden();
        $this->actingAs($member)->get($this->tmPage('index'))->assertForbidden();
    }

    public function test_the_workspace_owner_gets_in_without_a_role(): void
    {
        $this->actingAs($this->taskWorkspace->owner)
            ->getJson($this->tmRoute('spaces.index'))
            ->assertOk();
    }

    public function test_a_new_space_belongs_to_the_workspace_in_the_url(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.store'), ['name' => 'Ops'])
            ->assertCreated()
            ->assertJsonPath('data.workspace_id', $this->taskWorkspace->id);

        $this->assertSame($this->taskWorkspace->id, Space::sole()->workspace_id);
    }

    public function test_spaces_and_tasks_of_another_workspace_are_not_listed(): void
    {
        $user = User::factory()->create();
        $here = $this->taskIn($this->listIn($this->spaceOwnedBy($user)));

        $elsewhere = Space::factory()->withDefaultStatuses()->create([
            'workspace_id' => Workspace::factory()->create()->id,
            'owner_id' => $user->id,
        ]);
        $this->taskIn($this->listIn($elsewhere));

        $this->actingAs($user)
            ->getJson($this->tmRoute('spaces.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.workspace_id', $this->taskWorkspace->id);

        $this->actingAs($user)
            ->getJson($this->tmRoute('tasks.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $here->id);
    }

    public function test_a_record_from_another_workspace_answers_404_even_to_its_owner(): void
    {
        $user = User::factory()->create();
        $elsewhere = Space::factory()->withDefaultStatuses()->create([
            'workspace_id' => Workspace::factory()->create()->id,
            'owner_id' => $user->id,
        ]);
        $list = $this->listIn($elsewhere);
        $task = $this->taskIn($list);

        $this->actingAs($user)->getJson($this->tmRoute('spaces.show', $elsewhere))->assertNotFound();
        $this->actingAs($user)->getJson($this->tmRoute('lists.tasks.index', $list))->assertNotFound();
        $this->actingAs($user)->patchJson($this->tmRoute('tasks.update', $task), ['name' => 'x'])->assertNotFound();
        $this->actingAs($user)->get($this->tmPage('show', $task))->assertNotFound();

        $this->assertNotSame('x', $task->fresh()->name);
    }

    public function test_member_candidates_are_limited_to_the_workspace(): void
    {
        $owner = User::factory()->create();
        $space = $this->spaceOwnedBy($owner);
        $colleague = User::factory()->create(['name' => 'Zxqv Colleague']);
        $this->outsider()->update(['name' => 'Zxqv Outsider']);

        $this->actingAs($owner)
            ->getJson($this->tmRoute('spaces.member-candidates.index', [$space, 'filter' => ['search' => 'Zxqv']]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $colleague->id);
    }

    public function test_someone_outside_the_workspace_cannot_be_added_to_a_space(): void
    {
        $owner = User::factory()->create();
        $space = $this->spaceOwnedBy($owner);

        $this->actingAs($owner)
            ->postJson($this->tmRoute('spaces.members.store', $space), [
                'user_id' => $this->outsider()->id,
                'role' => SpaceRole::Member->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');

        $this->assertSame(0, $space->members()->count());
    }

    public function test_a_project_code_taken_in_another_workspace_is_still_free_here(): void
    {
        $user = User::factory()->create();
        $other = Space::factory()->create(['workspace_id' => Workspace::factory()->create()->id]);
        Folder::factory()->create(['space_id' => $other->id, 'code' => 'ART']);

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.folders.store', $this->spaceOwnedBy($user)), ['name' => 'Artemis'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'ART');
    }

    public function test_view_tasks_alone_can_read_but_not_change_anything(): void
    {
        $owner = User::factory()->create();
        $space = $this->spaceOwnedBy($owner);
        $list = $this->listIn($space);
        $task = $this->taskIn($list);

        // Even as the owner of a space, View Tasks alone is read-only.
        $viewer = $this->viewOnlyUser();
        $space->members()->attach($viewer, ['role' => SpaceRole::Admin->value]);

        $this->actingAs($viewer)->getJson($this->tmRoute('spaces.index'))->assertOk();
        $this->actingAs($viewer)->getJson($this->tmRoute('tasks.show', $task))->assertOk();
        $this->actingAs($viewer)->getJson($this->tmRoute('tasks.comments.index', $task))->assertOk();
        $this->actingAs($viewer)->get($this->tmPage('show', $task))->assertOk();

        $writes = [
            ['postJson', $this->tmRoute('spaces.store'), ['name' => 'Ops']],
            ['patchJson', $this->tmRoute('spaces.update', $space), ['name' => 'Renamed']],
            ['postJson', $this->tmRoute('spaces.lists.store', $space), ['name' => 'Backlog']],
            ['postJson', $this->tmRoute('lists.tasks.store', $list), ['name' => 'New']],
            ['patchJson', $this->tmRoute('tasks.update', $task), ['name' => 'Renamed']],
            ['deleteJson', $this->tmRoute('tasks.destroy', $task), []],
            ['postJson', $this->tmRoute('tasks.comments.store', $task), ['body' => 'Hi']],
        ];

        foreach ($writes as [$method, $url, $body]) {
            $this->actingAs($viewer)->{$method}($url, $body)
                ->assertForbidden()
                ->assertJsonPath('message', 'You need the Manage Tasks permission to make changes.');
        }

        $this->assertSame(1, Space::query()->count());
        $this->assertNotSame('Renamed', $task->fresh()->name);
        $this->assertSame(0, $task->comments()->count());
    }

    public function test_manage_tasks_does_not_override_the_space_role(): void
    {
        $owner = User::factory()->create();
        $space = $this->spaceOwnedBy($owner);
        $list = $this->listIn($space);

        // Holds Manage Tasks (every test user does) but is only a viewer here.
        $viewer = User::factory()->create();
        $space->members()->attach($viewer, ['role' => SpaceRole::Viewer->value]);

        $this->actingAs($viewer)
            ->postJson($this->tmRoute('lists.tasks.store', $list), ['name' => 'New'])
            ->assertForbidden();
    }
}
