<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Modules\Billing\Models\WorkspaceBillingDetail;

/** A workspace member whose role carries exactly $permissions. */
function billingMemberWithPermissions(Workspace $workspace, array $permissions): User
{
    $user = User::factory()->create();

    $role = Role::create([
        'workspace_id' => $workspace->id,
        'name' => 'Role '.uniqid(),
    ]);

    foreach ($permissions as $name) {
        $permission = Permission::firstOrCreate(['name' => $name], ['category' => 'Billing']);
        DB::table('role_permissions')->insert([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
    }

    $workspace->users()->attach($user->id, ['role_id' => $role->id]);

    return $user;
}

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'owner_id' => $this->owner->id,
        'billing_module_enabled' => true,
    ]);
    $this->editUrl = route('billing-settings.edit', ['workspace' => $this->workspace->slug]);
    $this->updateUrl = route('billing-settings.update', ['workspace' => $this->workspace->slug]);
    $this->payload = [
        'billing_name' => 'Meta Digitrading Enterprise Co',
        'billing_address' => "123 Ayala Ave\nMakati City, 1226",
        'billing_email' => 'accounts@example.com',
    ];
});

it('forbids a member without the permission from viewing billing settings', function () {
    $user = billingMemberWithPermissions($this->workspace, []);

    $this->actingAs($user)->get($this->editUrl)->assertForbidden();
});

it('lets a member with view permission read the billing settings', function () {
    $user = billingMemberWithPermissions($this->workspace, [
        PermissionEnum::ViewBillingSettings->value,
    ]);

    $this->actingAs($user)->get($this->editUrl)->assertOk();
});

it('forbids a view-only member from saving billing settings', function () {
    $user = billingMemberWithPermissions($this->workspace, [
        PermissionEnum::ViewBillingSettings->value,
    ]);

    $this->actingAs($user)->put($this->updateUrl, $this->payload)->assertForbidden();

    expect(WorkspaceBillingDetail::where('workspace_id', $this->workspace->id)->exists())->toBeFalse();
});

it('lets a member with manage permission save billing settings', function () {
    $user = billingMemberWithPermissions($this->workspace, [
        PermissionEnum::ViewBillingSettings->value,
        PermissionEnum::ManageBillingSettings->value,
    ]);

    $this->actingAs($user)
        ->put($this->updateUrl, $this->payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $setting = WorkspaceBillingDetail::where('workspace_id', $this->workspace->id)->first();

    expect($setting->billing_name)->toBe('Meta Digitrading Enterprise Co')
        ->and($setting->billing_email)->toBe('accounts@example.com')
        ->and($setting->billing_address)->toContain('Ayala Ave');
});

it('still allows the workspace owner through', function () {
    $this->actingAs($this->owner)
        ->put($this->updateUrl, $this->payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});

it('updates the existing row instead of creating a second one', function () {
    WorkspaceBillingDetail::create([
        'workspace_id' => $this->workspace->id,
        'billing_name' => 'Old Name',
    ]);

    $this->actingAs($this->owner)->put($this->updateUrl, $this->payload)->assertRedirect();

    expect(WorkspaceBillingDetail::where('workspace_id', $this->workspace->id)->count())->toBe(1)
        ->and(WorkspaceBillingDetail::where('workspace_id', $this->workspace->id)->value('billing_name'))
        ->toBe('Meta Digitrading Enterprise Co');
});

it('rejects a malformed billing email', function () {
    $this->actingAs($this->owner)
        ->put($this->updateUrl, [...$this->payload, 'billing_email' => 'not-an-email'])
        ->assertSessionHasErrors('billing_email');
});

it('accepts blank billing details', function () {
    $this->actingAs($this->owner)
        ->put($this->updateUrl, [
            'billing_name' => null,
            'billing_address' => null,
            'billing_email' => null,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});

it('404s when the billing module is switched off for the workspace', function () {
    $this->workspace->update(['billing_module_enabled' => false]);

    $this->actingAs($this->owner)->get($this->editUrl)->assertNotFound();
    $this->actingAs($this->owner)->put($this->updateUrl, $this->payload)->assertNotFound();
});

it('strips billing permissions from a member when the module is off', function () {
    $this->workspace->update(['billing_module_enabled' => false]);

    expect($this->workspace->fresh()->disabledPermissionCategories())->toContain('Billing');
});

it('keeps billing permissions available when the module is on', function () {
    expect($this->workspace->fresh()->disabledPermissionCategories())->not->toContain('Billing');
});
