<?php

namespace Tests\Feature\TaskManagement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Models\Space;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SpacePolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The full permission matrix: role => [ability => allowed].
     *
     * @return array<string, array{SpaceRole, array<string, bool>}>
     */
    public static function roleMatrix(): array
    {
        return [
            'owner' => [SpaceRole::Owner, [
                'view' => true, 'update' => true, 'delete' => true,
                'manageStructure' => true, 'manageTasks' => true,
            ]],
            'admin' => [SpaceRole::Admin, [
                'view' => true, 'update' => true, 'delete' => false,
                'manageStructure' => true, 'manageTasks' => true,
            ]],
            'member' => [SpaceRole::Member, [
                'view' => true, 'update' => false, 'delete' => false,
                'manageStructure' => false, 'manageTasks' => true,
            ]],
            'viewer' => [SpaceRole::Viewer, [
                'view' => true, 'update' => false, 'delete' => false,
                'manageStructure' => false, 'manageTasks' => false,
            ]],
        ];
    }

    /**
     * @param  array<string, bool>  $abilities
     */
    #[DataProvider('roleMatrix')]
    public function test_role_grants_exactly_its_abilities(SpaceRole $role, array $abilities): void
    {
        $user = User::factory()->create();

        $space = $role === SpaceRole::Owner
            ? Space::factory()->create(['owner_id' => $user->id])
            : Space::factory()->withMember($user, $role)->create();

        foreach ($abilities as $ability => $allowed) {
            $this->assertSame(
                $allowed,
                Gate::forUser($user)->allows($ability, $space),
                "Role [{$role->value}] should ".($allowed ? 'allow' : 'deny')." [{$ability}]."
            );
        }
    }

    public function test_a_non_member_is_denied_every_ability(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->create();

        foreach (['view', 'update', 'delete', 'manageStructure', 'manageTasks'] as $ability) {
            $this->assertFalse(Gate::forUser($user)->allows($ability, $space));
        }
    }

    public function test_a_non_member_is_denied_as_not_found_so_the_space_stays_hidden(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->create();

        $this->assertSame(404, Gate::forUser($user)->inspect('view', $space)->status());
    }

    public function test_an_insufficient_role_is_denied_with_403_because_the_space_is_visible(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->withMember($user, SpaceRole::Member)->create();

        $this->assertSame(403, Gate::forUser($user)->inspect('update', $space)->status());
    }

    public function test_any_authenticated_user_may_create_a_space(): void
    {
        $this->assertTrue(Gate::forUser(User::factory()->create())->allows('create', Space::class));
    }
}
