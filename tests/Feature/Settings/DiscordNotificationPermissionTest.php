<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;

/** A workspace member whose role carries exactly $permissions. */
function memberWithPermissions(Workspace $workspace, array $permissions): User
{
    $user = User::factory()->create();

    $role = Role::create([
        'workspace_id' => $workspace->id,
        'name' => 'Role '.uniqid(),
    ]);

    foreach ($permissions as $name) {
        $permission = Permission::firstOrCreate(['name' => $name], ['category' => 'Settings']);
        DB::table('role_permissions')->insert([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
    }

    $workspace->users()->attach($user->id, ['role_id' => $role->id]);

    return $user;
}

beforeEach(function () {
    // The workspace owner is implicitly all-powerful, so the subjects below are
    // plain members of a workspace someone else owns.
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'owner_id' => $this->owner->id,
        'discord_notifications_module_enabled' => true,
    ]);
    $this->url = route('notifications.update', ['workspace' => $this->workspace->slug]);
    $this->payload = [
        'deliveries_webhook_url' => null,
        'awaiting_webhook_url' => null,
        'deliveries_enabled' => true,
        'deliveries_send_at' => '09:00',
        'awaiting_enabled' => true,
        'awaiting_send_at' => '10:00',
    ];
});

it('forbids a member without the permission from viewing the settings', function () {
    $user = memberWithPermissions($this->workspace, []);

    $this->actingAs($user)
        ->get(route('notifications.edit', ['workspace' => $this->workspace->slug]))
        ->assertForbidden();
});

it('forbids a member without the permission from saving a webhook url', function () {
    $user = memberWithPermissions($this->workspace, []);

    $this->actingAs($user)->put($this->url, $this->payload)->assertForbidden();
});

it('allows a member granted the permission to save', function () {
    $user = memberWithPermissions($this->workspace, [
        PermissionEnum::ManageDiscordNotifications->value,
    ]);

    $this->actingAs($user)
        ->put($this->url, $this->payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});

it('still allows the workspace owner through', function () {
    $this->actingAs($this->owner)
        ->put($this->url, $this->payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});
