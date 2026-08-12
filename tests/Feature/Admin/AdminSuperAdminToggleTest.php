<?php

use App\Models\User;

it('blocks users who are not super admins', function () {
    $target = User::factory()->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('admin.users.update-super-admin', $target), [
            'is_super_admin' => true,
        ])
        ->assertRedirect(route('dashboard'));

    expect($target->fresh()->is_super_admin)->toBeFalse();
});

it('grants super admin access', function () {
    $admin = User::factory()->superAdmin()->create();
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.update-super-admin', $target), [
            'is_super_admin' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($target->fresh()->is_super_admin)->toBeTrue();
});

it('verifies an unverified email on grant so the panel is reachable', function () {
    $admin = User::factory()->superAdmin()->create();
    $target = User::factory()->unverified()->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.update-super-admin', $target), [
            'is_super_admin' => true,
        ]);

    expect($target->fresh()->hasVerifiedEmail())->toBeTrue();

    // The whole point: the account can now reach what it was just granted.
    $this->actingAs($target->fresh())
        ->get(route('admin.users.index'))
        ->assertOk();
});

it('leaves an already-verified timestamp alone', function () {
    $admin = User::factory()->superAdmin()->create();
    $target = User::factory()->create(['email_verified_at' => now()->subYear()]);
    $original = $target->email_verified_at;

    $this->actingAs($admin)
        ->patch(route('admin.users.update-super-admin', $target), [
            'is_super_admin' => true,
        ]);

    expect($target->fresh()->email_verified_at->eq($original))->toBeTrue();
});

it('does not verify an email when revoking', function () {
    $admin = User::factory()->superAdmin()->create();
    $target = User::factory()->unverified()->superAdmin()->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.update-super-admin', $target), [
            'is_super_admin' => false,
        ]);

    expect($target->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('revokes super admin access from someone else', function () {
    $admin = User::factory()->superAdmin()->create();
    $target = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.update-super-admin', $target), [
            'is_super_admin' => false,
        ])
        ->assertSessionHasNoErrors();

    expect($target->fresh()->is_super_admin)->toBeFalse();
});

it('refuses to let a super admin revoke their own access', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.update-super-admin', $admin), [
            'is_super_admin' => false,
        ])
        ->assertSessionHasErrors('is_super_admin');

    expect($admin->fresh()->is_super_admin)->toBeTrue();
});

it('requires the is_super_admin flag', function () {
    $admin = User::factory()->superAdmin()->create();
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.update-super-admin', $target), [])
        ->assertSessionHasErrors('is_super_admin');

    expect($target->fresh()->is_super_admin)->toBeFalse();
});

it('writes a security audit entry for a grant', function () {
    $admin = User::factory()->superAdmin()->create();
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.update-super-admin', $target), [
            'is_super_admin' => true,
        ]);

    $this->assertDatabaseHas('activity_logs', [
        'category' => 'security',
        'action_type' => 'user.super_admin.granted',
        'user_id' => $admin->id,
    ]);
});
