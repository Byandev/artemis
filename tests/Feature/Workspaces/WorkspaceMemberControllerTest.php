<?php

use App\Models\Role;
use App\Models\User;

test('owner can view the members page', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/members")
        ->assertOk();
});

test('non-member is forbidden from viewing the members page', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get("/workspaces/{$workspace->slug}/members")
        ->assertForbidden();
});

test('owner can update a member role', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = makeWorkspaceMember($workspace, 'member');
    $newRole = Role::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/members")
        ->put("/workspaces/{$workspace->slug}/members/{$member->id}", [
            'role_id' => $newRole->id,
        ])
        ->assertRedirect();

    expect($workspace->users()->where('user_id', $member->id)->first()->pivot->role_id)
        ->toBe($newRole->id);
});

test('cannot change the owner role', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $newRole = Role::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/members")
        ->put("/workspaces/{$workspace->slug}/members/{$owner->id}", [
            'role_id' => $newRole->id,
        ])
        ->assertSessionHasErrors('error');
});

test('owner can remove a member', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = makeWorkspaceMember($workspace);

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/members")
        ->delete("/workspaces/{$workspace->slug}/members/{$member->id}")
        ->assertRedirect();

    expect($workspace->fresh()->hasMember($member))->toBeFalse();
});

test('cannot remove the workspace owner', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->from("/workspaces/{$workspace->slug}/members")
        ->delete("/workspaces/{$workspace->slug}/members/{$owner->id}")
        ->assertSessionHasErrors('error');

    expect($workspace->fresh()->hasMember($owner))->toBeTrue();
});

test('a member can self-leave', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = makeWorkspaceMember($workspace);

    $this->actingAs($member)
        ->delete("/workspaces/{$workspace->slug}/members/{$member->id}")
        ->assertRedirect();

    expect($workspace->fresh()->hasMember($member))->toBeFalse();
});

// ----- Filter & sort coverage -----

function memberNamesFromInertia($response): array
{
    return collect($response->getOriginalContent()->getData()['page']['props']['members']['data'])
        ->pluck('name')->all();
}

test('members index filter[search] matches partial name or email', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    $a = User::factory()->create(['name' => 'Findable Person', 'email' => 'a@example.test']);
    $b = User::factory()->create(['name' => 'Other', 'email' => 'findme@example.test']);
    $c = User::factory()->create(['name' => 'Different', 'email' => 'c@example.test']);
    $w->users()->attach([$a->id, $b->id, $c->id], ['role' => 'member']);

    $names = memberNamesFromInertia(
        $this->actingAs($owner)->get("/workspaces/{$w->slug}/members?filter[search]=find")->assertOk()
    );
    expect($names)->toContain('Findable Person', 'Other')
        ->and($names)->not->toContain('Different');
});

test('members index sort=email returns ascending', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    $a = User::factory()->create(['email' => 'aaa-x@example.test']);
    $z = User::factory()->create(['email' => 'zzz-x@example.test']);
    $m = User::factory()->create(['email' => 'mmm-x@example.test']);
    $w->users()->attach([$a->id, $z->id, $m->id], ['role' => 'member']);

    $emails = collect(
        $this->actingAs($owner)
            ->get("/workspaces/{$w->slug}/members?sort=email")
            ->assertOk()
            ->getOriginalContent()->getData()['page']['props']['members']['data']
    )->pluck('email')->all();

    $ours = array_values(array_filter($emails, fn ($e) => str_ends_with($e, '-x@example.test')));
    expect($ours)->toBe(['aaa-x@example.test', 'mmm-x@example.test', 'zzz-x@example.test']);
});

test('members index sort=-email returns descending', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    $a = User::factory()->create(['email' => 'aaa@example.test']);
    $z = User::factory()->create(['email' => 'zzz@example.test']);
    $w->users()->attach([$a->id, $z->id], ['role' => 'member']);

    $emails = collect(
        $this->actingAs($owner)
            ->get("/workspaces/{$w->slug}/members?sort=-email")
            ->assertOk()
            ->getOriginalContent()->getData()['page']['props']['members']['data']
    )->pluck('email')->all();

    $ours = array_values(array_filter($emails, fn ($e) => in_array($e, ['aaa@example.test', 'zzz@example.test'])));
    expect($ours)->toBe(['zzz@example.test', 'aaa@example.test']);
});

test('members index per_page paginates', function () {
    ['user' => $owner, 'workspace' => $w] = makeWorkspaceWithOwner();
    foreach (range(1, 4) as $_) {
        $u = User::factory()->create();
        $w->users()->attach($u->id, ['role' => 'member']);
    }

    $response = $this->actingAs($owner)
        ->get("/workspaces/{$w->slug}/members?per_page=2")
        ->assertOk();

    $data = $response->getOriginalContent()->getData()['page']['props']['members'];
    expect($data['per_page'])->toBe(2);
});
