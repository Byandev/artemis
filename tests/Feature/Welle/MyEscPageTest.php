<?php

use App\Enums\Permission;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| My ESC
|--------------------------------------------------------------------------
|
| The page itself is still blank, so what there is to assert is the way in:
| the Welle module toggle decides whether the page exists for a workspace at
| all, and the "View My ESC" grant decides who inside it gets to read it.
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
