<?php

namespace Tests\Feature\TaskManagement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Models\Folder;
use Modules\TaskManagement\Models\Space;
use Modules\TaskManagement\Models\TaskList;
use Tests\Feature\TaskManagement\Concerns\InteractsWithSpaces;
use Tests\TestCase;

class TaskListControllerTest extends TestCase
{
    use InteractsWithSpaces, RefreshDatabase;

    public function test_index_returns_every_list_in_the_space_including_folderless_ones(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $folder = $this->folderIn($space);
        $inFolder = $this->listIn($space, $folder);
        $loose = $this->listIn($space);
        TaskList::factory()->create();

        $response = $this->actingAs($user)->getJson($this->tmRoute('spaces.lists.index', $space));

        $response->assertOk();
        $this->assertEqualsCanonicalizing(
            [$inFolder->id, $loose->id],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_index_can_be_narrowed_to_a_single_folder(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $folder = $this->folderIn($space);
        $inFolder = $this->listIn($space, $folder);
        $this->listIn($space);

        $response = $this->actingAs($user)->getJson(
            $this->tmRoute('spaces.lists.index', [$space, 'folder_id' => $folder->id])
        );

        $response->assertOk();
        $this->assertSame([$inFolder->id], array_column($response->json('data'), 'id'));
    }

    public function test_store_creates_a_list_inside_a_folder(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $folder = $this->folderIn($space);

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.lists.store', $space), [
                'name' => 'Backlog',
                'folder_id' => $folder->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.folder_id', $folder->id)
            ->assertJsonPath('data.space_id', $space->id);
    }

    public function test_store_creates_a_list_directly_under_the_space_when_no_folder_is_given(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.lists.store', $space), ['name' => 'Inbox'])
            ->assertCreated()
            ->assertJsonPath('data.folder_id', null);
    }

    public function test_store_returns_422_when_the_folder_belongs_to_another_space(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $foreignFolder = Folder::factory()->create();

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.lists.store', $space), [
                'name' => 'Backlog',
                'folder_id' => $foreignFolder->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('folder_id');
    }

    public function test_store_returns_403_for_a_member(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceWhereUserIs($user, SpaceRole::Member);

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.lists.store', $space), ['name' => 'Backlog'])
            ->assertForbidden();
    }

    public function test_show_returns_404_for_a_list_in_another_space(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson($this->tmRoute('lists.show', TaskList::factory()->create()))
            ->assertNotFound();
    }

    public function test_update_moves_the_list_into_another_folder_of_the_same_space(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $list = $this->listIn($space);
        $folder = $this->folderIn($space);

        $this->actingAs($user)
            ->patchJson($this->tmRoute('lists.update', $list), ['folder_id' => $folder->id])
            ->assertOk()
            ->assertJsonPath('data.folder_id', $folder->id);

        $this->assertSame($folder->id, $list->fresh()->folder_id);
    }

    public function test_update_returns_422_when_moving_the_list_into_a_foreign_folder(): void
    {
        $user = User::factory()->create();
        $list = $this->listIn($this->spaceOwnedBy($user));

        $this->actingAs($user)
            ->patchJson($this->tmRoute('lists.update', $list), [
                'folder_id' => Folder::factory()->create()->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('folder_id');
    }

    public function test_destroy_deletes_the_list_and_its_tasks(): void
    {
        $user = User::factory()->create();
        $list = $this->listIn($this->spaceOwnedBy($user));
        $this->taskIn($list);

        $this->actingAs($user)
            ->deleteJson($this->tmRoute('lists.destroy', $list))
            ->assertNoContent();

        $this->assertNull($list->fresh());
        $this->assertDatabaseEmpty('tasks');
    }

    public function test_index_returns_404_for_a_space_the_user_does_not_belong_to(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson($this->tmRoute('spaces.lists.index', Space::factory()->create()))
            ->assertNotFound();
    }
}
