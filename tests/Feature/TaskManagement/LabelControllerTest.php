<?php

namespace Tests\Feature\TaskManagement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Models\Label;
use Tests\Feature\TaskManagement\Concerns\InteractsWithSpaces;
use Tests\TestCase;

class LabelControllerTest extends TestCase
{
    use InteractsWithSpaces, RefreshDatabase;

    public function test_index_returns_only_the_labels_of_the_space(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $label = $this->labelIn($space);
        Label::factory()->create();

        $response = $this->actingAs($user)->getJson($this->tmRoute('spaces.labels.index', $space));

        $response->assertOk();
        $this->assertSame([$label->id], array_column($response->json('data'), 'id'));
    }

    public function test_store_creates_a_label_and_returns_201(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.labels.store', $space), [
                'name' => 'bug',
                'color' => '#ef4444',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'bug');

        $this->assertSame(1, $space->labels()->count());
    }

    public function test_store_returns_422_for_a_duplicate_name_in_the_same_space(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        Label::factory()->create(['space_id' => $space->id, 'name' => 'bug']);

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.labels.store', $space), ['name' => 'bug'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_store_allows_the_same_name_in_a_different_space(): void
    {
        $user = User::factory()->create();
        Label::factory()->create(['name' => 'bug']);

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.labels.store', $this->spaceOwnedBy($user)), ['name' => 'bug'])
            ->assertCreated();
    }

    public function test_store_returns_403_for_a_viewer(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceWhereUserIs($user, SpaceRole::Viewer);

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.labels.store', $space), ['name' => 'bug'])
            ->assertForbidden();
    }

    public function test_destroy_deletes_the_label_and_detaches_it_from_tasks(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $label = $this->labelIn($space);
        $task = $this->taskIn($this->listIn($space));
        $task->labels()->attach($label);

        $this->actingAs($user)
            ->deleteJson($this->tmRoute('labels.destroy', $label))
            ->assertNoContent();

        $this->assertNull($label->fresh());
        $this->assertDatabaseEmpty('task_label_task');
    }

    public function test_destroy_returns_404_for_a_label_in_another_space(): void
    {
        $this->actingAs(User::factory()->create())
            ->deleteJson($this->tmRoute('labels.destroy', Label::factory()->create()))
            ->assertNotFound();
    }
}
