<?php

use App\Models\Team;
use App\Models\User;

test('owner can view teams index', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    Team::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/teams")
        ->assertOk();
});

test('non-member cannot view teams', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get("/workspaces/{$workspace->slug}/teams")
        ->assertForbidden();
});

test('owner can create a team with members', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $member1 = makeWorkspaceMember($workspace);
    $member2 = makeWorkspaceMember($workspace);

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/teams")
        ->post("/workspaces/{$workspace->slug}/teams", [
            'name' => 'Engineering',
            'members' => [$member1->id, $member2->id],
        ])
        ->assertRedirect();

    $team = Team::where('workspace_id', $workspace->id)->where('name', 'Engineering')->first();
    expect($team)->not->toBeNull();
    expect($team->members()->count())->toBe(2);
});

test('store rejects duplicate team name within workspace', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    Team::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Existing']);

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/teams")
        ->post("/workspaces/{$workspace->slug}/teams", [
            'name' => 'Existing',
        ])
        ->assertSessionHasErrors('name');
});

test('store ignores members that do not belong to the workspace', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $foreignUser = User::factory()->create();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/teams")
        ->post("/workspaces/{$workspace->slug}/teams", [
            'name' => 'Outsiders',
            'members' => [$foreignUser->id],
        ])
        ->assertRedirect();

    $team = Team::where('name', 'Outsiders')->first();
    expect($team->members()->count())->toBe(0);
});

test('owner can update a team', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $team = Team::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Old']);
    $member = makeWorkspaceMember($workspace);

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/teams")
        ->put("/workspaces/{$workspace->slug}/teams/{$team->id}", [
            'name' => 'New',
            'members' => [$member->id],
        ])
        ->assertRedirect();

    $team->refresh();
    expect($team->name)->toBe('New');
    expect($team->members()->count())->toBe(1);
});

test('cannot update a team from a different workspace', function () {
    ['user' => $owner, 'workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();
    $foreignTeam = Team::factory()->create(['workspace_id' => $workspaceB->id]);

    $this->actingAs($owner)
        ->put("/workspaces/{$workspaceA->slug}/teams/{$foreignTeam->id}", [
            'name' => 'Hijacked',
        ])
        ->assertForbidden();
});

test('owner can delete a team', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $team = Team::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($owner)
        ->delete("/workspaces/{$workspace->slug}/teams/{$team->id}")
        ->assertRedirect();

    expect(Team::find($team->id))->toBeNull();
});

test('store rejects when name exceeds 255 characters', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/teams")
        ->post("/workspaces/{$workspace->slug}/teams", ['name' => str_repeat('x', 256)])
        ->assertSessionHasErrors('name');
});

test('store rejects when members contains a non-existent user id', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/teams")
        ->post("/workspaces/{$workspace->slug}/teams", [
            'name' => 'New', 'members' => [99999],
        ])
        ->assertSessionHasErrors('members.0');
});

test('non-admin members cannot create a team', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = makeWorkspaceMember($workspace, 'member');

    $this->actingAs($member)
        ->from("/workspaces/{$workspace->slug}/teams")
        ->post("/workspaces/{$workspace->slug}/teams", ['name' => 'X'])
        ->assertForbidden();
});

test('cannot delete a team from a different workspace', function () {
    ['user' => $owner, 'workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();
    $foreignTeam = Team::factory()->create(['workspace_id' => $workspaceB->id]);

    $this->actingAs($owner)
        ->delete("/workspaces/{$workspaceA->slug}/teams/{$foreignTeam->id}")
        ->assertForbidden();

    expect(Team::find($foreignTeam->id))->not->toBeNull();
});

// ----- Filter & sort coverage -----

function teamsFromInertia($response): array
{
    return collect($response->getOriginalContent()->getData()['page']['props']['teams']['data'])
        ->pluck('name')->all();
}

test('teams index filter[search] partial match on name', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    Team::factory()->create(['workspace_id' => $w->id, 'name' => 'Engineering Squad']);
    Team::factory()->create(['workspace_id' => $w->id, 'name' => 'Marketing']);

    $names = teamsFromInertia(
        $this->actingAs($owner)->get("/workspaces/{$w->slug}/teams?filter[search]=engineering")->assertOk()
    );
    expect($names)->toBe(['Engineering Squad']);
});

test('teams index sort=name returns ascending', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    Team::factory()->create(['workspace_id' => $w->id, 'name' => 'Charlie']);
    Team::factory()->create(['workspace_id' => $w->id, 'name' => 'Alpha']);
    Team::factory()->create(['workspace_id' => $w->id, 'name' => 'Bravo']);

    $names = teamsFromInertia(
        $this->actingAs($owner)->get("/workspaces/{$w->slug}/teams?sort=name")->assertOk()
    );
    expect($names)->toBe(['Alpha', 'Bravo', 'Charlie']);
});

test('teams index sort=members_count returns by member count ascending', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();

    $bigTeam = Team::factory()->create(['workspace_id' => $w->id, 'name' => 'Big']);
    $smallTeam = Team::factory()->create(['workspace_id' => $w->id, 'name' => 'Small']);

    $u1 = makeWorkspaceMember($w);
    $u2 = makeWorkspaceMember($w);
    $u3 = makeWorkspaceMember($w);
    $bigTeam->members()->attach([$u1->id, $u2->id, $u3->id]);
    $smallTeam->members()->attach([$u1->id]);

    $names = teamsFromInertia(
        $this->actingAs($owner)->get("/workspaces/{$w->slug}/teams?sort=members_count")->assertOk()
    );
    expect($names)->toBe(['Small', 'Big']);
});

test('teams index sort=-members_count returns by member count descending', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    $a = Team::factory()->create(['workspace_id' => $w->id, 'name' => 'A']);
    $b = Team::factory()->create(['workspace_id' => $w->id, 'name' => 'B']);
    $u = makeWorkspaceMember($w);
    $b->members()->attach([$u->id]);

    $names = teamsFromInertia(
        $this->actingAs($owner)->get("/workspaces/{$w->slug}/teams?sort=-members_count")->assertOk()
    );
    expect($names[0])->toBe('B');
});

test('teams index defaults to created_at descending', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    Team::factory()->create(['workspace_id' => $w->id, 'name' => 'First']);
    sleep(1);
    Team::factory()->create(['workspace_id' => $w->id, 'name' => 'Second']);

    $names = teamsFromInertia(
        $this->actingAs($owner)->get("/workspaces/{$w->slug}/teams")->assertOk()
    );
    expect($names[0])->toBe('Second');
});
