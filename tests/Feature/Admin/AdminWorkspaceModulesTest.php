<?php

use App\Models\User;
use App\Models\Workspace;

/**
 * Every flag `AdminWorkspaceController@updateModules` validates. All of them are
 * `required`, so the payload has to be sent whole on each request.
 */
function moduleTogglePayload(Workspace $workspace, array $overrides = []): array
{
    $keys = [
        'inventory_module_enabled',
        'finance_module_enabled',
        'products_module_enabled',
        'teams_module_enabled',
        'checklist_module_enabled',
        'csr_module_enabled',
        'rmo_module_enabled',
        'leaderboard_module_enabled',
        'botcake_module_enabled',
        'creatives_module_enabled',
        'meta_ads_module_enabled',
        'gencys_module_enabled',
        'is_gencys_partner',
        'sales_marketing_dashboard_module_enabled',
        'video_editor_dashboard_module_enabled',
        'csr_dashboard_module_enabled',
        'sim_gateway_module_enabled',
        'ad_spend_goals_module_enabled',
        'billing_module_enabled',
    ];

    $payload = [];

    foreach ($keys as $key) {
        $payload[$key] = (bool) $workspace->{$key};
    }

    return array_merge($payload, $overrides);
}

it('defaults the billing module to off for a new workspace', function () {
    $workspace = Workspace::factory()->create();

    expect($workspace->fresh()->billing_module_enabled)->toBeFalse();
});

it('lets a super admin enable the billing module for a workspace', function () {
    $admin = User::factory()->superAdmin()->create();
    $workspace = Workspace::factory()->create();

    $this->actingAs($admin)
        ->put(
            route('admin.workspaces.update-modules', $workspace),
            moduleTogglePayload($workspace, ['billing_module_enabled' => true])
        )
        ->assertRedirect();

    expect($workspace->fresh()->billing_module_enabled)->toBeTrue();
});

it('lets a super admin disable the billing module again', function () {
    $admin = User::factory()->superAdmin()->create();
    $workspace = Workspace::factory()->create(['billing_module_enabled' => true]);

    $this->actingAs($admin)
        ->put(
            route('admin.workspaces.update-modules', $workspace),
            moduleTogglePayload($workspace, ['billing_module_enabled' => false])
        )
        ->assertRedirect();

    expect($workspace->fresh()->billing_module_enabled)->toBeFalse();
});

it('rejects a module update that omits the billing flag', function () {
    $admin = User::factory()->superAdmin()->create();
    $workspace = Workspace::factory()->create();

    $payload = moduleTogglePayload($workspace);
    unset($payload['billing_module_enabled']);

    $this->actingAs($admin)
        ->put(route('admin.workspaces.update-modules', $workspace), $payload)
        ->assertSessionHasErrors('billing_module_enabled');
});

it('does not let a non-admin toggle the billing module', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $this->actingAs($user)
        ->put(
            route('admin.workspaces.update-modules', $workspace),
            moduleTogglePayload($workspace, ['billing_module_enabled' => true])
        )
        // CheckAdmin bounces non-super-admins to the dashboard rather than 403ing.
        ->assertRedirect(route('dashboard'));

    expect($workspace->fresh()->billing_module_enabled)->toBeFalse();
});
