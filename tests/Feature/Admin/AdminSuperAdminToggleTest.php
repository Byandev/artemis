<?php

use App\Models\ActivityLog;
use App\Models\User;

/**
 * Granting and revoking global Super Admin from the admin users page.
 *
 * The route sits behind the `admin` middleware, so only a Super Admin gets
 * here at all. The rest of these are about not locking everyone out.
 */
beforeEach(function () {
    $this->admin = User::factory()->superAdmin()->create();
});

function toggleSuperAdmin(User $target, bool $grant)
{
    return test()->patch(route('admin.users.update-super-admin', $target), [
        'is_super_admin' => $grant,
    ]);
}

it('promotes an ordinary user to super admin', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin);
    toggleSuperAdmin($user, true)->assertRedirect();

    expect($user->fresh()->is_super_admin)->toBeTrue();
});

it('demotes another super admin', function () {
    $other = User::factory()->superAdmin()->create();

    $this->actingAs($this->admin);
    toggleSuperAdmin($other, false)->assertRedirect();

    expect($other->fresh()->is_super_admin)->toBeFalse();
});

it('refuses to let a super admin demote themselves', function () {
    // Another admin exists, so this is refused on the self rule alone.
    User::factory()->superAdmin()->create();

    $this->actingAs($this->admin);
    toggleSuperAdmin($this->admin, false)->assertSessionHasErrors('is_super_admin');

    expect($this->admin->fresh()->is_super_admin)->toBeTrue();
});

it('refuses to demote the last super admin', function () {
    $other = User::factory()->superAdmin()->create();

    // Down to one, then try to remove that one from a different session.
    $this->actingAs($this->admin);
    toggleSuperAdmin($other, false);

    $this->actingAs($other);
    toggleSuperAdmin($this->admin, false)->assertSessionHasErrors('is_super_admin');

    expect($this->admin->fresh()->is_super_admin)->toBeTrue();
});

it('blocks users who are not super admins', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($user);
    toggleSuperAdmin($target, true)->assertRedirect(route('dashboard'));

    expect($target->fresh()->is_super_admin)->toBeFalse();
});

it('blocks guests', function () {
    $target = User::factory()->create();

    toggleSuperAdmin($target, true)->assertRedirect(route('login'));

    expect($target->fresh()->is_super_admin)->toBeFalse();
});

it('requires a boolean', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin);

    test()->patch(route('admin.users.update-super-admin', $user), [])
        ->assertSessionHasErrors('is_super_admin');

    test()->patch(route('admin.users.update-super-admin', $user), [
        'is_super_admin' => 'maybe',
    ])->assertSessionHasErrors('is_super_admin');
});

it('records the change in the activity log', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin);
    toggleSuperAdmin($user, true);

    // The catch-all audit middleware covers state-changing web requests, which
    // is what makes a promotion traceable after the fact.
    expect(ActivityLog::where('user_id', $this->admin->id)->exists())
        ->toBeTrue();
});
