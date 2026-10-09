<?php

namespace Tests\Feature\TaskManagement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Models\Space;
use Tests\Feature\TaskManagement\Concerns\InteractsWithSpaces;
use Tests\TestCase;

class SpaceMemberControllerTest extends TestCase
{
    use InteractsWithSpaces, RefreshDatabase;

    public function test_index_returns_the_members_and_the_owner_with_their_roles(): void
    {
        $owner = User::factory()->create(['name' => 'Zoe Owner']);
        $member = User::factory()->create(['name' => 'Ada Member']);
        $space = $this->spaceOwnedBy($owner);
        $space->members()->attach($member, ['role' => SpaceRole::Member->value]);

        $response = $this->actingAs($owner)
            ->getJson($this->tmRoute('spaces.members.index', $space));

        $response->assertOk();
        $this->assertSame(
            ['Ada Member', 'Zoe Owner'],
            array_column($response->json('data'), 'name'),
        );
        $this->assertSame(
            [SpaceRole::Member->value, SpaceRole::Owner->value],
            array_column($response->json('data'), 'role'),
        );
    }

    public function test_index_lists_the_owner_once_as_owner_even_with_a_lesser_pivot_row(): void
    {
        $owner = User::factory()->create();
        $space = $this->spaceOwnedBy($owner);
        $space->members()->attach($owner, ['role' => SpaceRole::Viewer->value]);

        $this->actingAs($owner)
            ->getJson($this->tmRoute('spaces.members.index', $space))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.role', SpaceRole::Owner->value);
    }

    public function test_index_returns_404_for_a_user_outside_the_space(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson($this->tmRoute('spaces.members.index', Space::factory()->create()))
            ->assertNotFound();
    }

    public function test_an_admin_can_add_an_existing_account_by_id(): void
    {
        $admin = User::factory()->create();
        $space = $this->spaceWhereUserIs($admin, SpaceRole::Admin);
        $invitee = User::factory()->create();

        $this->actingAs($admin)
            ->postJson($this->tmRoute('spaces.members.store', $space), [
                'user_id' => $invitee->id,
                'role' => SpaceRole::Member->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.id', $invitee->id)
            ->assertJsonPath('data.role', SpaceRole::Member->value);

        $this->assertSame(
            SpaceRole::Member,
            $space->fresh()->roleFor($invitee),
        );
    }

    public function test_adding_an_unknown_account_is_rejected(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->postJson($this->tmRoute('spaces.members.store', $this->spaceOwnedBy($owner)), [
                'user_id' => 9999,
                'role' => SpaceRole::Member->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');
    }

    public function test_adding_someone_already_in_the_space_is_rejected(): void
    {
        $owner = User::factory()->create();
        $space = $this->spaceOwnedBy($owner);
        $member = User::factory()->create();
        $space->members()->attach($member, ['role' => SpaceRole::Viewer->value]);

        $this->actingAs($owner)
            ->postJson($this->tmRoute('spaces.members.store', $space), [
                'user_id' => $member->id,
                'role' => SpaceRole::Member->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');

        $this->actingAs($owner)
            ->postJson($this->tmRoute('spaces.members.store', $space), [
                'user_id' => $owner->id,
                'role' => SpaceRole::Member->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');
    }

    public function test_the_owner_role_cannot_be_granted(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();

        $this->actingAs($owner)
            ->postJson($this->tmRoute('spaces.members.store', $this->spaceOwnedBy($owner)), [
                'user_id' => $invitee->id,
                'role' => SpaceRole::Owner->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_a_member_cannot_add_members(): void
    {
        $member = User::factory()->create();
        $space = $this->spaceWhereUserIs($member, SpaceRole::Member);
        $invitee = User::factory()->create();

        $this->actingAs($member)
            ->postJson($this->tmRoute('spaces.members.store', $space), [
                'user_id' => $invitee->id,
                'role' => SpaceRole::Member->value,
            ])
            ->assertForbidden();

        $this->assertNull($space->fresh()->roleFor($invitee));
    }

    public function test_a_user_outside_the_space_gets_404_rather_than_403(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->postJson($this->tmRoute('spaces.members.store', Space::factory()->create()), [
                'user_id' => User::factory()->create()->id,
                'role' => SpaceRole::Member->value,
            ])
            ->assertNotFound();
    }

    public function test_an_admin_can_change_a_members_role(): void
    {
        $admin = User::factory()->create();
        $space = $this->spaceWhereUserIs($admin, SpaceRole::Admin);
        $member = User::factory()->create();
        $space->members()->attach($member, ['role' => SpaceRole::Viewer->value]);

        $this->actingAs($admin)
            ->patchJson($this->tmRoute('spaces.members.update', [$space, $member]), [
                'role' => SpaceRole::Admin->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.role', SpaceRole::Admin->value);

        $this->assertSame(SpaceRole::Admin, $space->fresh()->roleFor($member));
    }

    public function test_the_owner_cannot_be_re_roled(): void
    {
        $owner = User::factory()->create();
        $space = $this->spaceOwnedBy($owner);

        $this->actingAs($owner)
            ->patchJson($this->tmRoute('spaces.members.update', [$space, $owner]), [
                'role' => SpaceRole::Viewer->value,
            ])
            ->assertUnprocessable();

        $this->assertSame(SpaceRole::Owner, $space->fresh()->roleFor($owner));
    }

    public function test_re_roling_someone_who_is_not_a_member_returns_404(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->patchJson($this->tmRoute('spaces.members.update', [$this->spaceOwnedBy($owner), User::factory()->create()]), [
                'role' => SpaceRole::Viewer->value,
            ])
            ->assertNotFound();
    }

    public function test_an_admin_can_remove_a_member(): void
    {
        $admin = User::factory()->create();
        $space = $this->spaceWhereUserIs($admin, SpaceRole::Admin);
        $member = User::factory()->create();
        $space->members()->attach($member, ['role' => SpaceRole::Member->value]);

        $this->actingAs($admin)
            ->deleteJson($this->tmRoute('spaces.members.destroy', [$space, $member]))
            ->assertNoContent();

        $this->assertNull($space->fresh()->roleFor($member));
    }

    public function test_removing_a_member_clears_their_assignments_in_that_space_only(): void
    {
        $admin = User::factory()->create();
        $space = $this->spaceWhereUserIs($admin, SpaceRole::Admin);
        $member = User::factory()->create();
        $space->members()->attach($member, ['role' => SpaceRole::Member->value]);

        $task = $this->taskIn($this->listIn($space));
        $task->assignees()->attach($member);

        $otherSpace = $this->spaceWhereUserIs($member, SpaceRole::Member);
        $otherTask = $this->taskIn($this->listIn($otherSpace));
        $otherTask->assignees()->attach($member);

        $this->actingAs($admin)
            ->deleteJson($this->tmRoute('spaces.members.destroy', [$space, $member]))
            ->assertNoContent();

        $this->assertSame(0, $task->assignees()->count());
        $this->assertSame(1, $otherTask->assignees()->count());
    }

    public function test_the_owner_cannot_be_removed(): void
    {
        $owner = User::factory()->create();
        $space = $this->spaceOwnedBy($owner);

        $this->actingAs($owner)
            ->deleteJson($this->tmRoute('spaces.members.destroy', [$space, $owner]))
            ->assertUnprocessable();

        $this->assertSame(SpaceRole::Owner, $space->fresh()->roleFor($owner));
    }

    public function test_a_viewer_cannot_remove_a_member(): void
    {
        $viewer = User::factory()->create();
        $space = $this->spaceWhereUserIs($viewer, SpaceRole::Viewer);
        $member = User::factory()->create();
        $space->members()->attach($member, ['role' => SpaceRole::Member->value]);

        $this->actingAs($viewer)
            ->deleteJson($this->tmRoute('spaces.members.destroy', [$space, $member]))
            ->assertForbidden();

        $this->assertSame(SpaceRole::Member, $space->fresh()->roleFor($member));
    }
}
