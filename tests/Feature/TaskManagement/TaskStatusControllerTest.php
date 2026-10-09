<?php

namespace Tests\Feature\TaskManagement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Enums\TaskStatusType;
use Modules\TaskManagement\Models\TaskStatus;
use Tests\Feature\TaskManagement\Concerns\InteractsWithSpaces;
use Tests\TestCase;

class TaskStatusControllerTest extends TestCase
{
    use InteractsWithSpaces, RefreshDatabase;

    public function test_index_returns_the_statuses_of_the_space_in_position_order(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);

        $response = $this->actingAs($user)->getJson($this->tmRoute('spaces.statuses.index', $space));

        $response->assertOk();
        $this->assertSame(
            array_column(TaskStatus::DEFAULTS, 'slug'),
            array_column($response->json('data'), 'slug'),
        );
    }

    public function test_store_creates_a_user_defined_status_and_returns_201(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.statuses.store', $space), [
                'name' => 'In Review',
                'type' => TaskStatusType::Active->value,
                'color' => '#eab308',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'In Review')
            ->assertJsonPath('data.slug', 'in-review')
            ->assertJsonPath('data.type', TaskStatusType::Active->value);

        $this->assertSame(4, $space->statuses()->count());
    }

    public function test_store_returns_422_for_a_duplicate_slug_in_the_same_space(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.statuses.store', $space), [
                'name' => 'To Do',
                'type' => TaskStatusType::NotStarted->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_store_returns_422_for_an_unknown_type(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.statuses.store', $this->spaceOwnedBy($user)), [
                'name' => 'Blocked',
                'type' => 'nope',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_store_returns_403_for_a_member(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceWhereUserIs($user, SpaceRole::Member);

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.statuses.store', $space), [
                'name' => 'Blocked',
                'type' => TaskStatusType::Active->value,
            ])
            ->assertForbidden();
    }

    public function test_marking_a_status_default_clears_the_previous_default(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $previous = $this->defaultStatusOf($space);
        $target = $space->statuses()->where('slug', 'in-progress')->sole();

        $this->actingAs($user)
            ->patchJson($this->tmRoute('statuses.update', $target), ['is_default' => true])
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse($previous->fresh()->is_default);
        $this->assertTrue($target->fresh()->is_default);
    }

    public function test_update_renames_a_status_and_regenerates_its_slug(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $status = $space->statuses()->where('slug', 'in-progress')->sole();

        $this->actingAs($user)
            ->patchJson($this->tmRoute('statuses.update', $status), ['name' => 'Under Review'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Under Review')
            ->assertJsonPath('data.slug', 'under-review');
    }

    public function test_update_changes_the_colour_and_recomputes_completeness_from_the_type(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $status = $space->statuses()->where('slug', 'in-progress')->sole();

        $this->assertFalse($status->type->isComplete());

        $this->actingAs($user)
            ->patchJson($this->tmRoute('statuses.update', $status), [
                'color' => '#b8863b',
                'type' => TaskStatusType::Done->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.color', '#b8863b')
            ->assertJsonPath('data.is_complete', true);
    }

    public function test_update_reorders_a_status_by_position(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $first = $space->statuses()->orderBy('position')->first();
        $second = $space->statuses()->orderBy('position')->skip(1)->first();

        $this->actingAs($user)
            ->patchJson($this->tmRoute('statuses.update', $second), ['position' => $first->position])
            ->assertOk();

        $this->actingAs($user)
            ->patchJson($this->tmRoute('statuses.update', $first), ['position' => $second->position])
            ->assertOk();

        $this->assertSame(
            [$second->id, $first->id],
            $space->statuses()->orderBy('position')->orderBy('id')->pluck('id')->take(2)->all(),
        );
    }

    public function test_update_returns_422_for_a_name_taken_by_another_status(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $status = $space->statuses()->where('slug', 'in-progress')->sole();

        $this->actingAs($user)
            ->patchJson($this->tmRoute('statuses.update', $status), ['name' => 'To Do'])
            ->assertJsonValidationErrorFor('name');
    }

    public function test_update_returns_403_for_a_member(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceWhereUserIs($user, SpaceRole::Member);
        $status = $space->statuses()->where('slug', 'in-progress')->sole();

        $this->actingAs($user)
            ->patchJson($this->tmRoute('statuses.update', $status), ['name' => 'Blocked'])
            ->assertForbidden();
    }

    public function test_destroy_deletes_an_unused_status(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $status = $space->statuses()->where('slug', 'in-progress')->sole();

        $this->actingAs($user)
            ->deleteJson($this->tmRoute('statuses.destroy', $status))
            ->assertNoContent();

        $this->assertNull($status->fresh());
    }

    public function test_destroy_returns_409_when_tasks_still_use_the_status(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $list = $this->listIn($space);
        $status = $this->defaultStatusOf($space);
        $this->taskIn($list);

        $this->actingAs($user)
            ->deleteJson($this->tmRoute('statuses.destroy', $status))
            ->assertStatus(409)
            ->assertJsonPath('message', __('This status still has tasks assigned to it.'));

        $this->assertNotNull($status->fresh());
    }

    public function test_destroy_returns_404_for_a_status_in_another_space(): void
    {
        $this->actingAs(User::factory()->create())
            ->deleteJson($this->tmRoute('statuses.destroy', TaskStatus::factory()->create()))
            ->assertNotFound();
    }
}
