<?php

namespace Tests\Feature\TaskManagement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Models\Space;
use Tests\Feature\TaskManagement\Concerns\InteractsWithSpaces;
use Tests\TestCase;

class SpaceMemberCandidateControllerTest extends TestCase
{
    use InteractsWithSpaces, RefreshDatabase;

    public function test_it_matches_accounts_on_name_and_email(): void
    {
        $owner = User::factory()->create();
        $space = $this->spaceOwnedBy($owner);
        $byName = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'al@example.com']);
        $byEmail = User::factory()->create(['name' => 'Grace Hopper', 'email' => 'ada@example.net']);
        User::factory()->create(['name' => 'Alan Turing', 'email' => 'alan@example.com']);

        $response = $this->actingAs($owner)
            ->getJson($this->tmRoute('spaces.member-candidates.index', [$space, 'filter' => ['search' => 'ada']]));

        $response->assertOk();
        $this->assertSame(
            [$byName->id, $byEmail->id],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_it_excludes_the_owner_and_existing_members(): void
    {
        $owner = User::factory()->create(['name' => 'Ada Owner']);
        $space = $this->spaceOwnedBy($owner);
        $member = User::factory()->create(['name' => 'Ada Member']);
        $space->members()->attach($member, ['role' => SpaceRole::Member->value]);
        $candidate = User::factory()->create(['name' => 'Ada Candidate']);

        $this->actingAs($owner)
            ->getJson($this->tmRoute('spaces.member-candidates.index', [$space, 'filter' => ['search' => 'Ada']]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $candidate->id);
    }

    public function test_a_search_term_of_at_least_two_characters_is_required(): void
    {
        $owner = User::factory()->create();
        $space = $this->spaceOwnedBy($owner);

        $this->actingAs($owner)
            ->getJson($this->tmRoute('spaces.member-candidates.index', $space))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter.search');

        $this->actingAs($owner)
            ->getJson($this->tmRoute('spaces.member-candidates.index', [$space, 'filter' => ['search' => 'a']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter.search');
    }

    public function test_wildcards_in_the_term_are_matched_literally(): void
    {
        $owner = User::factory()->create();
        $space = $this->spaceOwnedBy($owner);
        User::factory()->create(['name' => 'Ada Lovelace']);

        $this->actingAs($owner)
            ->getJson($this->tmRoute('spaces.member-candidates.index', [$space, 'filter' => ['search' => '%%']]))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_member_cannot_search_for_candidates(): void
    {
        $member = User::factory()->create();
        $space = $this->spaceWhereUserIs($member, SpaceRole::Member);

        $this->actingAs($member)
            ->getJson($this->tmRoute('spaces.member-candidates.index', [$space, 'filter' => ['search' => 'ada']]))
            ->assertForbidden();
    }

    public function test_a_user_outside_the_space_gets_404_rather_than_403(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson($this->tmRoute('spaces.member-candidates.index', [Space::factory()->create(), 'filter' => ['search' => 'ada']]))
            ->assertNotFound();
    }
}
