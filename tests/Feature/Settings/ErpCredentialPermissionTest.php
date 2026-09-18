<?php

use App\Enums\Permission;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| ERP Credentials
|--------------------------------------------------------------------------
|
| Two switches decide who reaches the ERP credentials form: the workspace's
| Gencys ERP module toggle decides whether the page exists at all — the
| credentials are only ever read by that sync pipeline — and the "Manage ERP
| Credentials" grant decides who inside it gets to read and change them.
*/

beforeEach(function () {
    ['user' => $this->owner, 'workspace' => $this->workspace] = makeWorkspaceWithOwner();

    $this->workspace->update(['gencys_module_enabled' => true]);

    $this->url = route('erp-credentials.edit', ['workspace' => $this->workspace->slug]);
});

it('renders the page for a member holding the grant', function () {
    $member = makeMemberWithPermissions(
        $this->workspace,
        [Permission::ManageErpCredentials->value],
        'Settings',
    );

    $this->actingAs($member)
        ->get($this->url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/erp-credentials')
            ->where('workspace.slug', $this->workspace->slug),
        );
});

it('lets the owner in without the grant, as every other page does', function () {
    $this->actingAs($this->owner)->get($this->url)->assertOk();
});

it('forbids a member without the grant', function () {
    $member = makeWorkspaceMember($this->workspace);

    $this->actingAs($member)->get($this->url)->assertForbidden();
});

it('forbids someone who is not in the workspace', function () {
    $this->actingAs(User::factory()->create())->get($this->url)->assertForbidden();
});

it('lets a member granted the permission save credentials', function () {
    $member = makeMemberWithPermissions(
        $this->workspace,
        [Permission::ManageErpCredentials->value],
        'Settings',
    );

    $this->actingAs($member)
        ->put(route('erp-credentials.update', ['workspace' => $this->workspace->slug]), [
            'erp_username' => 'integration@example.com',
            'erp_password' => 'secret-password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($this->workspace->fresh()->erp_username)->toBe('integration@example.com');
});

it('forbids a member without the grant from saving credentials', function () {
    $member = makeWorkspaceMember($this->workspace);

    $this->actingAs($member)
        ->put(route('erp-credentials.update', ['workspace' => $this->workspace->slug]), [
            'erp_username' => 'integration@example.com',
            'erp_password' => 'secret-password',
        ])
        ->assertForbidden();

    expect($this->workspace->fresh()->erp_username)->toBeNull();
});

it('forbids a member without the grant from clearing the password', function () {
    $this->workspace->update(['erp_password' => 'secret-password']);

    $member = makeWorkspaceMember($this->workspace);

    $this->actingAs($member)
        ->delete(route('erp-credentials.destroy', ['workspace' => $this->workspace->slug]))
        ->assertForbidden();

    expect($this->workspace->fresh()->erp_password_set)->toBeTrue();
});

it('is gone while the gencys erp module is switched off, even for the owner', function () {
    // Owners hold '*', so only the module check stands between them and a form
    // pointed at an ERP the workspace does not run.
    $this->workspace->update(['gencys_module_enabled' => false]);

    $this->actingAs($this->owner)->get($this->url)->assertNotFound();
});

it('hides the grant from the role editor while the module is off', function () {
    expect($this->workspace->hiddenPermissionNames())
        ->not->toContain(Permission::ManageErpCredentials->value);

    $this->workspace->update(['gencys_module_enabled' => false]);

    expect($this->workspace->fresh()->hiddenPermissionNames())
        ->toContain(Permission::ManageErpCredentials->value);
});
