<?php

namespace Tests\Feature\TaskManagement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Models\Folder;
use Modules\TaskManagement\Models\Space;
use Tests\Feature\TaskManagement\Concerns\InteractsWithSpaces;
use Tests\TestCase;

class FolderControllerTest extends TestCase
{
    use InteractsWithSpaces, RefreshDatabase;

    public function test_index_returns_the_folders_of_the_space_in_position_order(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $second = Folder::factory()->create(['space_id' => $space->id, 'position' => 2, 'name' => 'Second']);
        $first = Folder::factory()->create(['space_id' => $space->id, 'position' => 1, 'name' => 'First']);
        Folder::factory()->create();

        $response = $this->actingAs($user)->getJson($this->tmRoute('spaces.folders.index', $space));

        $response->assertOk();
        $this->assertSame([$first->id, $second->id], array_column($response->json('data'), 'id'));
    }

    public function test_index_returns_404_for_a_space_the_user_does_not_belong_to(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson($this->tmRoute('spaces.folders.index', Space::factory()->create()))
            ->assertNotFound();
    }

    public function test_store_creates_a_folder_in_the_space_and_returns_201(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceWhereUserIs($user, SpaceRole::Admin);

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.folders.store', $space), ['name' => 'Q1'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Q1')
            ->assertJsonPath('data.space_id', $space->id);

        $this->assertSame(1, $space->folders()->count());
    }

    public function test_store_appends_the_folder_after_the_existing_ones(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        Folder::factory()->create(['space_id' => $space->id, 'position' => 7]);

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.folders.store', $space), ['name' => 'Q1'])
            ->assertCreated()
            ->assertJsonPath('data.position', 8);
    }

    public function test_store_returns_403_for_a_member(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceWhereUserIs($user, SpaceRole::Member);

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.folders.store', $space), ['name' => 'Q1'])
            ->assertForbidden();

        $this->assertDatabaseEmpty('task_folders');
    }

    public function test_store_returns_422_when_the_name_is_missing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.folders.store', $this->spaceOwnedBy($user)), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_update_renames_the_folder(): void
    {
        $user = User::factory()->create();
        $folder = $this->folderIn($this->spaceOwnedBy($user));

        $this->actingAs($user)
            ->patchJson($this->tmRoute('folders.update', $folder), ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');

        $this->assertSame('Renamed', $folder->fresh()->name);
    }

    public function test_update_returns_404_for_a_folder_in_another_space(): void
    {
        $folder = Folder::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patchJson($this->tmRoute('folders.update', $folder), ['name' => 'Renamed'])
            ->assertNotFound();
    }

    public function test_destroy_deletes_the_folder_and_its_lists(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $folder = $this->folderIn($space);
        $this->listIn($space, $folder);

        $this->actingAs($user)
            ->deleteJson($this->tmRoute('folders.destroy', $folder))
            ->assertNoContent();

        $this->assertNull($folder->fresh());
        $this->assertDatabaseEmpty('task_lists');
    }

    public function test_destroy_returns_403_for_a_viewer(): void
    {
        $user = User::factory()->create();
        $folder = $this->folderIn($this->spaceWhereUserIs($user, SpaceRole::Viewer));

        $this->actingAs($user)
            ->deleteJson($this->tmRoute('folders.destroy', $folder))
            ->assertForbidden();

        $this->assertNotNull($folder->fresh());
    }
}
