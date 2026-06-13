<?php

use App\Models\Page;
use App\Models\PageAccessGrant;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Access\PageAccessScope;

beforeEach(fn () => PageAccessScope::flush());

function grantPageToUser(Workspace $ws, User $user, Page $page): void
{
    PageAccessGrant::create([
        'workspace_id' => $ws->id,
        'grantee_type' => $user->getMorphClass(),
        'grantee_id' => $user->id,
        'page_id' => $page->id,
    ]);
}

function grantPageToTeam(Workspace $ws, Team $team, Page $page): void
{
    PageAccessGrant::create([
        'workspace_id' => $ws->id,
        'grantee_type' => $team->getMorphClass(),
        'grantee_id' => $team->id,
        'page_id' => $page->id,
    ]);
}

it('returns null (unrestricted) for the workspace owner', function () {
    ['workspace' => $ws, 'user' => $owner] = actingAsWorkspaceOwner();
    $page = Page::factory()->forWorkspace($ws)->create();
    grantPageToUser($ws, $owner, $page);

    expect(PageAccessScope::allowedPageIds($owner, $ws))->toBeNull();
});

it('returns null when the member has no grants', function () {
    ['workspace' => $ws] = actingAsWorkspaceOwner();
    $member = makeWorkspaceMember($ws);

    expect(PageAccessScope::allowedPageIds($member, $ws))->toBeNull();
});

it('returns the pages granted directly to the user', function () {
    ['workspace' => $ws] = actingAsWorkspaceOwner();
    $member = makeWorkspaceMember($ws);
    $granted = Page::factory()->forWorkspace($ws)->create();
    Page::factory()->forWorkspace($ws)->create(); // not granted

    grantPageToUser($ws, $member, $granted);

    expect(PageAccessScope::allowedPageIds($member, $ws))->toEqualCanonicalizing([$granted->id]);
});

it('inherits pages granted to a team the user belongs to', function () {
    ['workspace' => $ws] = actingAsWorkspaceOwner();
    $member = makeWorkspaceMember($ws);
    $team = Team::factory()->create(['workspace_id' => $ws->id]);
    $team->members()->attach($member->id);
    $teamPage = Page::factory()->forWorkspace($ws)->create();

    grantPageToTeam($ws, $team, $teamPage);

    expect(PageAccessScope::allowedPageIds($member, $ws))->toEqualCanonicalizing([$teamPage->id]);
});

it('unions the user grants with all their team grants', function () {
    ['workspace' => $ws] = actingAsWorkspaceOwner();
    $member = makeWorkspaceMember($ws);
    $team = Team::factory()->create(['workspace_id' => $ws->id]);
    $team->members()->attach($member->id);

    $userPage = Page::factory()->forWorkspace($ws)->create();
    $teamPage = Page::factory()->forWorkspace($ws)->create();

    grantPageToUser($ws, $member, $userPage);
    grantPageToTeam($ws, $team, $teamPage);

    expect(PageAccessScope::allowedPageIds($member, $ws))
        ->toEqualCanonicalizing([$userPage->id, $teamPage->id]);
});

it('ignores grants for teams the user is not in', function () {
    ['workspace' => $ws] = actingAsWorkspaceOwner();
    $member = makeWorkspaceMember($ws);
    $otherTeam = Team::factory()->create(['workspace_id' => $ws->id]); // member not attached
    $otherPage = Page::factory()->forWorkspace($ws)->create();

    grantPageToTeam($ws, $otherTeam, $otherPage);

    expect(PageAccessScope::allowedPageIds($member, $ws))->toBeNull();
});

it('does not leak grants from another workspace', function () {
    ['workspace' => $ws] = actingAsWorkspaceOwner();
    $member = makeWorkspaceMember($ws);

    $otherWs = Workspace::factory()->create();
    $otherPage = Page::factory()->forWorkspace($otherWs)->create();
    // A stray grant scoped to the other workspace.
    PageAccessGrant::create([
        'workspace_id' => $otherWs->id,
        'grantee_type' => $member->getMorphClass(),
        'grantee_id' => $member->id,
        'page_id' => $otherPage->id,
    ]);

    expect(PageAccessScope::allowedPageIds($member, $ws))->toBeNull();
});
