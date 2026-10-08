<?php

namespace Tests\Feature\TaskManagement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\TaskManagement\Enums\SpaceRole;
use Modules\TaskManagement\Models\Folder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\TaskManagement\Concerns\InteractsWithSpaces;
use Tests\TestCase;

class FolderCodeTest extends TestCase
{
    use InteractsWithSpaces, RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function nameProvider(): array
    {
        return [
            'ordinary name' => ['Artemis', 'ART'],
            'second ordinary name' => ['Matrix', 'MAT'],
            'lower case' => ['artemis', 'ART'],
            'two letters only' => ['QA', 'QAX'],
            'one letter' => ['R', 'RXX'],
            'punctuation between letters' => ['R&D', 'RDX'],
            'leading digits' => ['4Site', 'SIT'],
            'no letters at all' => ['42', 'XXX'],
            'leading space' => ['  ops team', 'OPS'],
        ];
    }

    #[DataProvider('nameProvider')]
    public function test_a_code_is_derived_from_the_project_name(string $name, string $expected): void
    {
        $this->assertSame($expected, Folder::deriveCodeFrom($name));
    }

    public function test_creating_a_project_derives_its_code_without_anyone_typing_one(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.folders.store', $space), ['name' => 'Artemis'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'ART');
    }

    /**
     * Artemis has no field for typing a code, so a derived code that is taken
     * moves to the next free variant rather than failing. A typed one that is
     * taken is still refused.
     */
    public function test_a_derived_code_that_is_taken_moves_to_the_next_free_variant(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);
        Folder::factory()->create(['space_id' => $space->id, 'code' => 'ART']);

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.folders.store', $space), ['name' => 'Artemis V2'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'ART2');

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.folders.store', $space), ['name' => 'Artemis V3', 'code' => 'ART'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_a_code_is_unique_across_spaces_not_only_within_one(): void
    {
        $user = User::factory()->create();
        Folder::factory()->create(['space_id' => $this->spaceOwnedBy($user)->id, 'code' => 'ART']);
        $other = $this->spaceWhereUserIs($user, SpaceRole::Admin);

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.folders.store', $other), ['name' => 'Artemis', 'code' => 'ART'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_a_code_can_be_chosen_and_later_edited(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);

        $folder = $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.folders.store', $space), ['name' => 'Matrix', 'code' => 'mtx'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'MTX')
            ->json('data.id');

        $this->actingAs($user)
            ->patchJson($this->tmRoute('folders.update', $folder), ['code' => 'MX2'])
            ->assertOk()
            ->assertJsonPath('data.code', 'MX2');
    }

    public function test_renaming_a_project_leaves_its_code_alone(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->create([
            'space_id' => $this->spaceOwnedBy($user)->id,
            'name' => 'Artemis',
            'code' => 'ART',
        ]);

        $this->actingAs($user)
            ->patchJson($this->tmRoute('folders.update', $folder), ['name' => 'Zephyr'])
            ->assertOk()
            ->assertJsonPath('data.code', 'ART');
    }

    public function test_the_reserved_code_cannot_be_taken_by_a_project(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.folders.store', $space), ['name' => 'Tasks', 'code' => Folder::UNASSIGNED_CODE])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_a_malformed_code_is_refused(): void
    {
        $user = User::factory()->create();
        $space = $this->spaceOwnedBy($user);

        $this->actingAs($user)
            ->postJson($this->tmRoute('spaces.folders.store', $space), ['name' => 'Artemis', 'code' => '1ST'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }
}
