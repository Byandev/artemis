<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

const EXTERNAL_TEAM_SHEET = 'https://docs.google.com/spreadsheets/d/18Bjq0JnI3Q8O2XQ669OjfMdRp8B_u6rmjsvA_dXrEpk/edit?usp=sharing';

/** A workspace member whose role carries "Manage RMO Settings". */
function externalTeamSettingsManager(Workspace $workspace): User
{
    $user = User::factory()->create();

    $role = Role::create([
        'workspace_id' => $workspace->id,
        'name' => 'Role '.uniqid(),
    ]);

    $permission = Permission::firstOrCreate(
        ['name' => PermissionEnum::ManageRmoSettings->value],
        ['category' => 'RTS'],
    );
    DB::table('role_permissions')->insert([
        'role_id' => $role->id,
        'permission_id' => $permission->id,
    ]);

    $workspace->users()->attach($user->id, ['role_id' => $role->id]);

    return $user;
}

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->owner->id]);
    subscribeWorkspace($this->workspace);
    $this->manager = externalTeamSettingsManager($this->workspace);
    $this->settingsUrl = route('rmo-settings.update', ['workspace' => $this->workspace->slug]);
});

function saveExternalTeamSettings(mixed $test, bool $enabled, ?string $url)
{
    return $test->actingAs($test->manager)->put($test->settingsUrl, [
        'enable_edit_previous_day' => false,
        'enable_bulk_status_update' => false,
        'enable_external_team_sync' => $enabled,
        'external_team_sheet_url' => $url,
    ]);
}

test('the settings page exposes and saves external team sync', function () {
    $this->actingAs($this->manager)
        ->get(route('rmo-settings.edit', ['workspace' => $this->workspace->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('settings.enable_external_team_sync', false)
            ->where('settings.external_team_sheet_url', null));

    saveExternalTeamSettings($this, true, EXTERNAL_TEAM_SHEET)->assertSessionHasNoErrors();

    $workspace = $this->workspace->fresh();
    expect($workspace->rmoExternalTeamSyncEnabled())->toBeTrue()
        ->and($workspace->rmoSetting->external_team_sheet_url)->toBe(EXTERNAL_TEAM_SHEET);

    saveExternalTeamSettings($this, false, EXTERNAL_TEAM_SHEET)->assertSessionHasNoErrors();

    expect($this->workspace->fresh()->rmoExternalTeamSyncEnabled())->toBeFalse();
});

test('turning external team sync on needs a sheet link', function () {
    saveExternalTeamSettings($this, true, null)->assertSessionHasErrors('external_team_sheet_url');

    expect($this->workspace->fresh()->rmoExternalTeamSyncEnabled())->toBeFalse();
});

test('the sheet link must be a Google Sheets link', function () {
    saveExternalTeamSettings($this, true, 'https://example.com/sheet')
        ->assertSessionHasErrors('external_team_sheet_url');
});

test('the RMO page only offers the external team filter while sync is on', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $url = route('public-page.rmo-management', ['workspace' => $this->workspace->slug]);

    $this->actingAs($admin)->get($url)
        ->assertInertia(fn ($page) => $page->where('enable_external_team_sync', false));

    $this->workspace->rmoSetting()->create([
        'enable_external_team_sync' => true,
        'external_team_sheet_url' => EXTERNAL_TEAM_SHEET,
    ]);

    $this->actingAs($admin)->get($url)
        ->assertInertia(fn ($page) => $page->where('enable_external_team_sync', true));
});

test('the settings page offers the cx / rider statuses and shows the default status map', function () {
    $rider = $this->workspace->rmoRiderStatuses()->pluck('id', 'name');

    $this->actingAs($this->manager)
        ->get(route('rmo-settings.edit', ['workspace' => $this->workspace->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('cxStatuses')
            ->has('riderStatuses')
            ->where('settings.external_team_status_map', [
                'confirmed' => "rider:{$rider['RIDER OTW']}",
                'delivered' => "rider:{$rider['DELIVERED']}",
                'returned' => "rider:{$rider['RETURNING']}",
            ]));
});

test('the external team status map saves, keeping a blank pick as tag nothing', function () {
    $cx = $this->workspace->rmoCxStatuses()->where('name', 'CANCELLED')->value('id');

    $this->actingAs($this->manager)->put($this->settingsUrl, [
        'enable_edit_previous_day' => false,
        'enable_bulk_status_update' => false,
        'external_team_status_map' => ['confirmed' => '', 'returned' => "cx:{$cx}"],
    ])->assertSessionHasNoErrors();

    // toEqual: MySQL's JSON column hands the keys back in its own order.
    expect($this->workspace->fresh()->rmoSetting->external_team_status_map)->toEqual([
        'confirmed' => '',
        'delivered' => '',
        'returned' => "cx:{$cx}",
    ]);
});

test('the external team status map rejects another workspace\'s status', function () {
    $other = Workspace::factory()->create(['owner_id' => $this->owner->id]);
    $foreign = $other->rmoRiderStatuses()->value('id');

    $this->actingAs($this->manager)->put($this->settingsUrl, [
        'enable_edit_previous_day' => false,
        'enable_bulk_status_update' => false,
        'external_team_status_map' => ['confirmed' => "rider:{$foreign}"],
    ])->assertSessionHasErrors('external_team_status_map.confirmed');
});
