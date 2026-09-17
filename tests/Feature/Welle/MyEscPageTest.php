<?php

use App\Enums\Permission;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| My ESC
|--------------------------------------------------------------------------
|
| The way in, and the one thing the page is given up front: the Welle module
| toggle decides whether the page exists for a workspace at all, the "View My
| ESC" grant decides who inside it gets to read it, and `connected` decides
| whether it opens on the figures or on "set up your Welle account first".
|
| The figures themselves are fetched card by card over XHR and are asserted
| against their own endpoints, not here.
*/

beforeEach(function () {
    ['user' => $this->owner, 'workspace' => $this->workspace] = makeWorkspaceWithOwner();

    $this->workspace->update(['welle_module_enabled' => true]);

    $this->url = route('workspaces.welle.my-esc', ['workspace' => $this->workspace->slug]);
});

it('renders the page for a member holding the grant', function () {
    $member = makeMemberWithPermissions($this->workspace, [Permission::ViewMyEsc->value], 'Welle');

    $this->actingAs($member)
        ->get($this->url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/welle/my-esc')
            ->where('workspace.slug', $this->workspace->slug),
        );
});

it('opens on the setup prompt while no welle account is connected', function () {
    // `connected` false is what puts the page on "set up your Welle account
    // first" instead of seven cards that could never fill.
    $this->actingAs($this->owner)
        ->get($this->url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('connected', false));
});

it('opens on the figures once a welle account is connected', function () {
    connectWelleAccount($this->owner);

    $this->actingAs($this->owner)
        ->get($this->url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('connected', true));
});

it('reads the signed-in user own account, not another member one', function () {
    // Credentials belong to the person: someone else's connected account does
    // not put this member's page on the figures.
    $member = makeMemberWithPermissions($this->workspace, [Permission::ViewMyEsc->value], 'Welle');

    connectWelleAccount($this->owner);

    $this->actingAs($member)
        ->get($this->url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('connected', false));
});

it('lets the owner in without the grant, as every other page does', function () {
    $this->actingAs($this->owner)->get($this->url)->assertOk();
});

it('is gone while the welle module is switched off', function () {
    $this->workspace->update(['welle_module_enabled' => false]);

    $this->actingAs($this->owner)->get($this->url)->assertNotFound();
});

it('forbids a member without the grant', function () {
    $member = makeWorkspaceMember($this->workspace);

    $this->actingAs($member)->get($this->url)->assertForbidden();
});

it('forbids someone who is not in the workspace', function () {
    $this->actingAs(User::factory()->create())->get($this->url)->assertForbidden();
});

it('hides the grant from the role editor while the module is off', function () {
    expect($this->workspace->hiddenPermissionNames())->not->toContain(Permission::ViewMyEsc->value);

    $this->workspace->update(['welle_module_enabled' => false]);

    expect($this->workspace->fresh()->hiddenPermissionNames())->toContain(Permission::ViewMyEsc->value);
});
