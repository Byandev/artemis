<?php

use App\Models\Page;
use App\Models\PageAccessGrant;
use App\Models\Team;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the per-member access page with current grants', function () {
    ['workspace' => $ws] = actingAsWorkspaceOwner();
    $member = makeWorkspaceMember($ws);
    $page = Page::factory()->forWorkspace($ws)->create();
    PageAccessGrant::create([
        'workspace_id' => $ws->id,
        'grantee_type' => $member->getMorphClass(),
        'grantee_id' => $member->id,
        'page_id' => $page->id,
    ]);

    $this->get(route('workspaces.access.users.edit', ['workspace' => $ws, 'user' => $member]))
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->component('workspaces/access/user')
            ->where('member.id', $member->id)
            ->has('pages')
            ->where('pageIds', [$page->id]));
});

it('renders the per-team access page', function () {
    ['workspace' => $ws] = actingAsWorkspaceOwner();
    $team = Team::factory()->create(['workspace_id' => $ws->id]);

    $this->get(route('workspaces.access.teams.edit', ['workspace' => $ws, 'team' => $team]))
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->component('workspaces/access/team')
            ->where('team.id', $team->id)
            ->has('pages'));
});

it('syncs page grants for a member', function () {
    ['workspace' => $ws] = actingAsWorkspaceOwner();
    $member = makeWorkspaceMember($ws);
    $p1 = Page::factory()->forWorkspace($ws)->create();
    $p2 = Page::factory()->forWorkspace($ws)->create();

    $this->put(route('workspaces.access.users.update', ['workspace' => $ws, 'user' => $member]), [
        'page_ids' => [$p1->id, $p2->id],
    ])->assertRedirect();

    expect(PageAccessGrant::where('grantee_type', $member->getMorphClass())
        ->where('grantee_id', $member->id)
        ->pluck('page_id')->map(fn ($id) => (int) $id)->all())
        ->toEqualCanonicalizing([$p1->id, $p2->id]);
});

it('replaces existing grants on re-sync and clears on empty', function () {
    ['workspace' => $ws] = actingAsWorkspaceOwner();
    $member = makeWorkspaceMember($ws);
    $p1 = Page::factory()->forWorkspace($ws)->create();
    $p2 = Page::factory()->forWorkspace($ws)->create();

    $this->put(route('workspaces.access.users.update', ['workspace' => $ws, 'user' => $member]), ['page_ids' => [$p1->id]]);
    $this->put(route('workspaces.access.users.update', ['workspace' => $ws, 'user' => $member]), ['page_ids' => [$p2->id]]);

    expect(PageAccessGrant::where('grantee_id', $member->id)->pluck('page_id')->map(fn ($id) => (int) $id)->all())
        ->toEqualCanonicalizing([$p2->id]);

    $this->put(route('workspaces.access.users.update', ['workspace' => $ws, 'user' => $member]), ['page_ids' => []]);

    expect(PageAccessGrant::where('grantee_id', $member->id)->count())->toBe(0);
});

it('syncs page grants for a team', function () {
    ['workspace' => $ws] = actingAsWorkspaceOwner();
    $team = Team::factory()->create(['workspace_id' => $ws->id]);
    $p1 = Page::factory()->forWorkspace($ws)->create();

    $this->put(route('workspaces.access.teams.update', ['workspace' => $ws, 'team' => $team]), [
        'page_ids' => [$p1->id],
    ])->assertRedirect();

    expect(PageAccessGrant::where('grantee_type', $team->getMorphClass())
        ->where('grantee_id', $team->id)
        ->pluck('page_id')->map(fn ($id) => (int) $id)->all())
        ->toEqualCanonicalizing([$p1->id]);
});

it('ignores page ids from another workspace', function () {
    ['workspace' => $ws] = actingAsWorkspaceOwner();
    $member = makeWorkspaceMember($ws);
    $mine = Page::factory()->forWorkspace($ws)->create();
    $foreign = Page::factory()->forWorkspace(Workspace::factory()->create())->create();

    $this->put(route('workspaces.access.users.update', ['workspace' => $ws, 'user' => $member]), [
        'page_ids' => [$mine->id, $foreign->id],
    ])->assertRedirect();

    expect(PageAccessGrant::where('grantee_id', $member->id)->pluck('page_id')->map(fn ($id) => (int) $id)->all())
        ->toEqualCanonicalizing([$mine->id]);
});

it('forbids a member without edit-members permission from changing grants', function () {
    ['workspace' => $ws] = actingAsWorkspaceOwner();
    $actor = makeWorkspaceMember($ws);          // plain member, no permissions
    $target = makeWorkspaceMember($ws);
    $page = Page::factory()->forWorkspace($ws)->create();

    $this->actingAs($actor)
        ->put(route('workspaces.access.users.update', ['workspace' => $ws, 'user' => $target]), [
            'page_ids' => [$page->id],
        ])
        ->assertForbidden();

    expect(PageAccessGrant::count())->toBe(0);
});
