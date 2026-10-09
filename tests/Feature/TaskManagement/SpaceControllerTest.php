<?php

namespace Tests\Feature\TaskManagement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Models\Space;
use Modules\TaskManagement\Models\TaskStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\TaskManagement\Concerns\InteractsWithSpaces;
use Tests\TestCase;

class SpaceControllerTest extends TestCase
{
    use InteractsWithSpaces, RefreshDatabase;

    public function test_index_returns_only_spaces_the_user_owns_or_belongs_to(): void
    {
        $user = User::factory()->create();
        $owned = $this->spaceOwnedBy($user);
        $joined = $this->spaceWhereUserIs($user, SpaceRole::Member);
        $foreign = Space::factory()->create();

        $response = $this->actingAs($user)->getJson($this->tmRoute('spaces.index'));

        $response->assertOk();
        $this->assertEqualsCanonicalizing(
            [$owned->id, $joined->id],
            array_column($response->json('data'), 'id'),
        );
        $this->assertNotContains($foreign->id, array_column($response->json('data'), 'id'));
    }

    public function test_index_returns_401_when_unauthenticated(): void
    {
        $this->getJson($this->tmRoute('spaces.index'))->assertUnauthorized();
    }

    public function test_store_creates_a_space_owned_by_the_user_and_returns_201(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson($this->tmRoute('spaces.store'), [
            'name' => 'Product',
            'description' => 'Product delivery',
            'color' => '#ff0000',
            'metadata' => ['client' => 'acme'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Product')
            ->assertJsonPath('data.metadata.client', 'acme')
            ->assertJsonPath('data.role', SpaceRole::Owner->value);

        $space = Space::sole();
        $this->assertSame($user->id, $space->owner_id);
    }

    public function test_store_seeds_the_default_statuses_for_the_new_space(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.store'), ['name' => 'Product'])
            ->assertCreated();

        $statuses = Space::sole()->statuses()->orderBy('position')->get();

        $this->assertSame(
            array_column(TaskStatus::DEFAULTS, 'slug'),
            $statuses->pluck('slug')->all(),
        );
        $this->assertSame('to-do', $statuses->firstWhere('is_default', true)->slug);
    }

    public function test_store_response_reports_the_stored_position_rather_than_null(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson($this->tmRoute('spaces.store'), ['name' => 'Product'])
            ->assertCreated()
            ->assertJsonPath('data.position', 0);

        $this->assertSame(0, Space::sole()->position);
    }

    public function test_store_returns_422_when_the_name_is_missing(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson($this->tmRoute('spaces.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_show_returns_the_space_with_its_role_for_the_member(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceWhereUserIs($user, SpaceRole::Viewer);

        $this->actingAs($user)
            ->getJson($this->tmRoute('spaces.show', $space))
            ->assertOk()
            ->assertJsonPath('data.id', $space->id)
            ->assertJsonPath('data.role', SpaceRole::Viewer->value);
    }

    public function test_show_returns_404_for_a_space_the_user_does_not_belong_to(): void
    {
        $space = Space::factory()->create();

        $this->actingAs(User::factory()->create())
            ->getJson($this->tmRoute('spaces.show', $space))
            ->assertNotFound();
    }

    public function test_update_changes_the_space_for_an_admin(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceWhereUserIs($user, SpaceRole::Admin);

        $this->actingAs($user)
            ->patchJson($this->tmRoute('spaces.update', $space), ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');

        $this->assertSame('Renamed', $space->fresh()->name);
    }

    public function test_update_returns_403_for_a_member(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceWhereUserIs($user, SpaceRole::Member);

        $this->actingAs($user)
            ->patchJson($this->tmRoute('spaces.update', $space), ['name' => 'Renamed'])
            ->assertForbidden();

        $this->assertNotSame('Renamed', $space->fresh()->name);
    }

    public function test_archive_flag_marks_the_space_archived(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);

        $this->actingAs($user)
            ->patchJson($this->tmRoute('spaces.update', $space), ['archived' => true])
            ->assertOk()
            ->assertJsonPath('data.archived', true);

        $this->assertNotNull($space->fresh()->archived_at);
    }

    public function test_destroy_deletes_the_space_for_the_owner(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        $list = $this->listIn($space);
        $this->taskIn($list);

        $this->actingAs($user)
            ->deleteJson($this->tmRoute('spaces.destroy', $space))
            ->assertNoContent();

        $this->assertNull($space->fresh());
        $this->assertDatabaseEmpty('tasks');
    }

    /**
     * @return array<string, array{SpaceRole}>
     */
    public static function nonOwnerRoles(): array
    {
        return [
            'admin' => [SpaceRole::Admin],
            'member' => [SpaceRole::Member],
            'viewer' => [SpaceRole::Viewer],
        ];
    }

    #[DataProvider('nonOwnerRoles')]
    public function test_destroy_returns_403_for_every_role_below_owner(SpaceRole $role): void
    {
        $user = User::factory()->create();
        $space = $this->spaceWhereUserIs($user, $role);

        $this->actingAs($user)
            ->deleteJson($this->tmRoute('spaces.destroy', $space))
            ->assertForbidden();

        $this->assertNotNull($space->fresh());
    }
}
