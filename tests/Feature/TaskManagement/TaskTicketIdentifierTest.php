<?php

namespace Tests\Feature\TaskManagement;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\TaskManagement\Models\Folder;
use Modules\TaskManagement\Models\Space;
use Modules\TaskManagement\Models\TaskList;
use Tests\Feature\TaskManagement\Concerns\InteractsWithSpaces;
use Tests\TestCase;

class TaskTicketIdentifierTest extends TestCase
{
    use InteractsWithSpaces, RefreshDatabase;

    public function test_numbering_starts_at_one_and_increases_by_one_within_a_project(): void
    {
        $list = $this->listInProject('ART');

        $tickets = collect(range(1, 3))
            ->map(fn (): ?string => $this->taskIn($list)->ticketIdentifier())
            ->all();

        $this->assertSame(['ART-1', 'ART-2', 'ART-3'], $tickets);
    }

    public function test_the_same_number_appears_in_different_projects(): void
    {
        $first = $this->taskIn($this->listInProject('ART'));
        $second = $this->taskIn($this->listInProject('MAT'));

        $this->assertSame('ART-1', $first->ticketIdentifier());
        $this->assertSame('MAT-1', $second->ticketIdentifier());
    }

    public function test_a_task_outside_any_project_takes_its_spaces_code(): void
    {
        $space = Space::factory()->withDefaultStatuses()->create(['name' => 'Artemis']);
        $loose = TaskList::factory()->create(['space_id' => $space->id, 'folder_id' => null]);

        $this->assertSame('ART', $space->code);
        $this->assertSame(['ART-1', 'ART-2'], [
            $this->taskIn($loose)->ticketIdentifier(),
            $this->taskIn($loose)->ticketIdentifier(),
        ]);
    }

    public function test_a_space_created_through_the_api_derives_its_code_from_its_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.store'), ['name' => 'Artemis'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'ART');
    }

    public function test_a_taken_code_moves_to_the_next_free_variant_instead_of_failing(): void
    {
        $user = User::factory()->create();
        Space::factory()->create(['name' => 'Artemis']);

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.store'), ['name' => 'Artist'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'ART2');

        // Folders share the namespace with spaces.
        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.folders.store', $this->spaceOwnedBy($user)), ['name' => 'Arts'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'ART3');
    }

    public function test_a_typed_space_code_must_be_free(): void
    {
        $user = User::factory()->create();
        Folder::factory()->create(['space_id' => $this->spaceOwnedBy($user)->id, 'code' => 'OPS']);

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.store'), ['name' => 'Operations', 'code' => 'ops'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.store'), ['name' => 'Fallback', 'code' => 'TSK'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_renaming_a_space_keeps_its_code_and_existing_tickets(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->withDefaultStatuses()->create(['name' => 'Artemis', 'owner_id' => $user->id]);
        $task = $this->taskIn($this->listIn($space));

        $this->actingAs($user)
            ->patchJson($this->tmRoute('spaces.update', $space), ['name' => 'Zephyr'])
            ->assertOk()
            ->assertJsonPath('data.code', 'ART');

        $this->actingAs($user)
            ->patchJson($this->tmRoute('spaces.update', $space), ['code' => 'ZEP'])
            ->assertOk()
            ->assertJsonPath('data.code', 'ZEP');

        $this->assertSame('ART-1', $task->fresh()->ticketIdentifier());
        $this->assertSame('ZEP-2', $this->taskIn($this->listIn($space))->ticketIdentifier());
    }

    public function test_the_identifier_survives_a_rename_a_move_and_an_archive(): void
    {
        $task = $this->taskIn($this->listInProject('ART'));
        $elsewhere = $this->listInProject('MAT');

        $task->update(['name' => 'Renamed']);
        $task->update(['task_list_id' => $elsewhere->id]);
        $task->update(['archived_at' => now()]);

        $this->assertSame('ART-1', $task->fresh()?->ticketIdentifier());
    }

    public function test_editing_a_project_code_leaves_existing_identifiers_alone(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->create(['space_id' => $this->spaceOwnedBy($user)->id, 'code' => 'ART']);
        $list = TaskList::factory()->inFolder($folder)->create();
        $existing = $this->taskIn($list);

        $this->actingAs($user)
            ->patchJson($this->tmRoute('folders.update', $folder), ['code' => 'ARM'])
            ->assertOk();

        $this->assertSame('ART-1', $existing->fresh()?->ticketIdentifier());
        $this->assertSame('ARM-2', $this->taskIn($list)->ticketIdentifier());
    }

    public function test_no_two_tasks_in_a_project_can_hold_the_same_identifier(): void
    {
        $this->assertTrue(
            $this->hasUniqueIndexOn('tasks', ['workspace_id', 'ticket_code', 'ticket_number']),
            'tasks must carry a unique index on (workspace_id, ticket_code, ticket_number)'
        );

        $list = $this->listInProject('ART');
        $this->taskIn($list);

        $tickets = collect(range(1, 25))->map(fn (): ?string => $this->taskIn($list)->ticketIdentifier());

        $this->assertCount(25, $tickets->unique());
    }

    public function test_the_identifier_is_available_wherever_a_task_is_read_programmatically(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->create(['space_id' => $this->spaceOwnedBy($user)->id, 'code' => 'ART']);
        $task = $this->taskIn(TaskList::factory()->inFolder($folder)->create());

        $this->actingAs($user)
            ->getJson($this->tmRoute('tasks.show', $task))
            ->assertOk()
            ->assertJsonPath('data.ticket', 'ART-1')
            ->assertJsonPath('data.ticket_code', 'ART')
            ->assertJsonPath('data.ticket_number', 1);
    }

    public function test_numbering_does_not_reuse_the_numbers_of_deleted_tasks(): void
    {
        $list = $this->listInProject('ART');
        $first = $this->taskIn($list);
        $second = $this->taskIn($list);
        $third = $this->taskIn($list);

        $second->delete();

        $this->assertSame(['ART-1', 'ART-3'], [$first->fresh()?->ticketIdentifier(), $third->fresh()?->ticketIdentifier()]);
        $this->assertSame('ART-4', $this->taskIn($list)->ticketIdentifier());
    }

    public function test_a_code_that_has_already_numbered_tickets_cannot_be_taken_by_another_project(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $folder = Folder::factory()->create(['space_id' => $space->id, 'code' => 'ART']);
        $this->taskIn(TaskList::factory()->inFolder($folder)->create());

        // The project frees ART by taking a new code.
        $this->actingAs($user)
            ->patchJson($this->tmRoute('folders.update', $folder), ['code' => 'ARM'])
            ->assertOk();

        // ART is retired, not recycled: ART-1 is already out in the world.
        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.folders.store', $space), ['name' => 'Artisan', 'code' => 'ART'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_a_project_can_keep_its_own_code_when_something_else_is_updated(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->create([
            'space_id' => $this->spaceOwnedBy($user)->id,
            'code' => 'ART',
        ]);
        $this->taskIn(TaskList::factory()->inFolder($folder)->create());

        $this->actingAs($user)
            ->patchJson($this->tmRoute('folders.update', $folder), ['name' => 'Artemis', 'code' => 'ART'])
            ->assertOk()
            ->assertJsonPath('data.code', 'ART');
    }

    /**
     * A list inside a project with the given code.
     */
    private function listInProject(string $code): TaskList
    {
        $folder = Folder::factory()->create([
            'space_id' => $this->spaceOwnedBy(User::factory()->create())->id,
            'code' => $code,
        ]);

        return TaskList::factory()->inFolder($folder)->create();
    }

    /**
     * @param  list<string>  $columns
     */
    private function hasUniqueIndexOn(string $table, array $columns): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['unique'] && $index['columns'] === $columns) {
                return true;
            }
        }

        return false;
    }

    public function test_another_workspace_numbers_the_same_code_independently(): void
    {
        $this->taskIn($this->listInProject('ART'));
        $this->taskIn($this->listInProject('ART2'));

        $elsewhere = Workspace::factory()->create();
        $space = Space::factory()->withDefaultStatuses()->create(['workspace_id' => $elsewhere->id]);
        $folder = Folder::factory()->create(['space_id' => $space->id, 'code' => 'ART']);
        $task = $this->taskIn(TaskList::factory()->inFolder($folder)->create());

        $this->assertSame($elsewhere->id, $task->workspace_id);
        $this->assertSame('ART-1', $task->ticketIdentifier());
    }
}
