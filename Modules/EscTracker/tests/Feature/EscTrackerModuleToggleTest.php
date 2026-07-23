<?php

use App\Models\User;
use App\Models\Workspace;

/**
 * Every module flag `updateModules` expects, so a test payload mirrors what the
 * admin form posts.
 */
function moduleTogglePayload(array $overrides = []): array
{
    $base = [];

    foreach ([
        'inventory', 'finance', 'products', 'teams', 'checklist', 'csr', 'rmo',
        'leaderboard', 'botcake', 'creatives', 'meta_ads', 'gencys',
    ] as $module) {
        $base[$module.'_module_enabled'] = true;
    }

    return array_merge($base, [
        'is_gencys_partner' => false,
        'sales_marketing_dashboard_module_enabled' => true,
        'video_editor_dashboard_module_enabled' => true,
        'csr_dashboard_module_enabled' => true,
        'esc_tracker_module_enabled' => true,
    ], $overrides);
}

test('an admin can enable the ESC Tracker module for a workspace', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $workspace = Workspace::factory()->forOwner($admin)->create([
        'esc_tracker_module_enabled' => false,
    ]);

    $this->actingAs($admin)
        ->put("/admin/workspaces/{$workspace->slug}/modules", moduleTogglePayload())
        ->assertRedirect();

    expect($workspace->fresh()->esc_tracker_module_enabled)->toBeTrue();
});

test('an admin can disable it again', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $workspace = Workspace::factory()->forOwner($admin)->create([
        'esc_tracker_module_enabled' => true,
    ]);

    $this->actingAs($admin)
        ->put("/admin/workspaces/{$workspace->slug}/modules", moduleTogglePayload([
            'esc_tracker_module_enabled' => false,
        ]))
        ->assertRedirect();

    expect($workspace->fresh()->esc_tracker_module_enabled)->toBeFalse();
});

test('a client on a stale bundle can still toggle other modules', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $workspace = Workspace::factory()->forOwner($admin)->create([
        'esc_tracker_module_enabled' => true,
        'finance_module_enabled' => false,
    ]);

    // Older bundles don't know the field exists — omitting it must not 422 the
    // whole form, and must leave the stored value alone.
    $payload = moduleTogglePayload();
    unset($payload['esc_tracker_module_enabled']);

    $this->actingAs($admin)
        ->put("/admin/workspaces/{$workspace->slug}/modules", $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($workspace->fresh()->finance_module_enabled)->toBeTrue();
    expect($workspace->fresh()->esc_tracker_module_enabled)->toBeTrue();
});

test('the ESC Tracker permission category is stripped when the module is off', function () {
    $workspace = Workspace::factory()->create(['esc_tracker_module_enabled' => false]);

    expect($workspace->disabledPermissionCategories())->toContain('ESC Tracker');

    $workspace->forceFill(['esc_tracker_module_enabled' => true])->save();

    expect($workspace->fresh()->disabledPermissionCategories())->not->toContain('ESC Tracker');
});
