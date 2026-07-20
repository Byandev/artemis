<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\MetaAds\Models\Ad;
use Modules\MetaAds\Models\AdAccount;
use Modules\MetaAds\Models\AdSet;
use Modules\MetaAds\Models\Campaign;
use Modules\MetaAds\Models\Insight;
use Modules\MetaAds\Models\User as MetaUser;

/**
 * Minimal graph for creator tagging: one connected meta user, one visible ad
 * account, and two ads (1002, 2002) each with today's insights.
 */
function seedCreatorAds($workspace): void
{
    $metaUser = MetaUser::create(['id' => 9101, 'name' => 'Connected User', 'access_token' => 'test-token']);
    $metaUser->workspaces()->attach($workspace->id);

    $account = AdAccount::create(['id' => 101, 'name' => 'Account A']);
    $metaUser->adAccounts()->attach($account->id);

    $campaign = Campaign::create(['id' => 1000, 'meta_ads_account_id' => 101, 'name' => 'Campaign 1000']);
    $set = AdSet::create(['id' => 1001, 'meta_ads_account_id' => 101, 'meta_ads_campaign_id' => 1000, 'name' => 'Ad Set 1000']);

    $today = Carbon::today()->toDateString();
    foreach ([1002, 2002] as $adId) {
        Ad::create(['id' => $adId, 'meta_ads_account_id' => 101, 'meta_ads_campaign_id' => 1000, 'meta_ads_set_id' => 1001, 'name' => "Ad {$adId}"]);
        Insight::create([
            'meta_ads_ad_id' => $adId,
            'date' => $today,
            'meta_ads_account_id' => 101,
            'meta_ads_campaign_id' => 1000,
            'meta_ads_set_id' => 1001,
            'spend' => 50,
            'impressions' => 1000,
            'clicks' => 50,
        ]);
    }
}

function creatorUrl($workspace, $ad): string
{
    return route('workspaces.metaads.ads-manager.ads.creator', ['workspace' => $workspace, 'ad' => $ad]);
}

function bulkCreatorUrl($workspace): string
{
    return route('workspaces.metaads.ads-manager.ads.creator.bulk', ['workspace' => $workspace]);
}

function creatorDataUrl($workspace, array $query = []): string
{
    return route('workspaces.metaads.ads-manager.data', ['workspace' => $workspace, ...$query]);
}

/**
 * A workspace member whose role carries exactly $permissions. Owners are
 * implicitly all-powerful, so permission gating is proven with plain members of
 * a workspace someone else owns.
 */
function creatorMemberWith(Workspace $workspace, array $permissions): User
{
    $user = User::factory()->create();
    $role = Role::create(['workspace_id' => $workspace->id, 'name' => 'Role '.uniqid()]);

    foreach ($permissions as $name) {
        $permission = Permission::firstOrCreate(['name' => $name], ['category' => 'Meta Ads']);
        DB::table('role_permissions')->insert([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
    }

    $workspace->users()->attach($user->id, ['role_id' => $role->id]);

    return $user;
}

it('assigns and clears an internal creator on an ad', function () {
    ['workspace' => $workspace, 'user' => $owner] = actingAsWorkspaceOwner();
    seedCreatorAds($workspace);

    $this->patchJson(creatorUrl($workspace, 1002), ['creator_id' => $owner->id])
        ->assertOk()
        ->assertJsonPath('creator.id', $owner->id)
        ->assertJsonPath('creator.name', $owner->name);

    expect(Ad::find(1002)->creator_id)->toBe($owner->id);

    $this->patchJson(creatorUrl($workspace, 1002), ['creator_id' => null])
        ->assertOk()
        ->assertJsonPath('creator', null);

    expect(Ad::find(1002)->creator_id)->toBeNull();
});

it('bulk assigns a creator across multiple ads', function () {
    ['workspace' => $workspace, 'user' => $owner] = actingAsWorkspaceOwner();
    seedCreatorAds($workspace);

    $this->postJson(bulkCreatorUrl($workspace), [
        'ad_ids' => ['1002', '2002'],
        'creator_id' => $owner->id,
    ])->assertOk()->assertJsonPath('ok', true);

    expect(Ad::find(1002)->creator_id)->toBe($owner->id)
        ->and(Ad::find(2002)->creator_id)->toBe($owner->id);
});

it('rejects a creator who is not a workspace member', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedCreatorAds($workspace);

    $outsider = User::factory()->create();

    $this->patchJson(creatorUrl($workspace, 1002), ['creator_id' => $outsider->id])
        ->assertStatus(422);

    expect(Ad::find(1002)->creator_id)->toBeNull();
});

it('does not tag ads outside the workspace accounts', function () {
    ['workspace' => $workspace, 'user' => $owner] = actingAsWorkspaceOwner();
    seedCreatorAds($workspace);

    AdAccount::create(['id' => 999, 'name' => 'Foreign']);
    Ad::create(['id' => 9002, 'meta_ads_account_id' => 999, 'meta_ads_campaign_id' => 1, 'meta_ads_set_id' => 1, 'name' => 'Foreign Ad']);

    $this->patchJson(creatorUrl($workspace, 9002), ['creator_id' => $owner->id])
        ->assertNotFound();
});

it('returns creator_id + creator_name on the ad grouping', function () {
    ['workspace' => $workspace, 'user' => $owner] = actingAsWorkspaceOwner();
    seedCreatorAds($workspace);
    Ad::whereKey(1002)->update(['creator_id' => $owner->id]);

    $data = $this->getJson(creatorDataUrl($workspace, ['group_by' => 'ad']))->assertOk()->json('rows.data');
    $byId = collect($data)->keyBy('id');

    expect((int) $byId['1002']['creator_id'])->toBe($owner->id)
        ->and($byId['1002']['creator_name'])->toBe($owner->name)
        ->and($byId['2002']['creator_id'])->toBeNull();
});

it('filters the ad grouping by creator and by unassigned', function () {
    ['workspace' => $workspace, 'user' => $owner] = actingAsWorkspaceOwner();
    seedCreatorAds($workspace);
    Ad::whereKey(1002)->update(['creator_id' => $owner->id]);

    $mine = $this->getJson(creatorDataUrl($workspace, ['group_by' => 'ad', 'creator_id' => (string) $owner->id]))
        ->assertOk()->json('rows.data');
    expect($mine)->toHaveCount(1)
        ->and($mine[0]['id'])->toBe('1002');

    $unassigned = $this->getJson(creatorDataUrl($workspace, ['group_by' => 'ad', 'creator_id' => 'unassigned']))
        ->assertOk()->json('rows.data');
    expect($unassigned)->toHaveCount(1)
        ->and($unassigned[0]['id'])->toBe('2002');
});

it('lets a read-only member see creator tags but not change them', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    seedCreatorAds($workspace);
    Ad::whereKey(1002)->update(['creator_id' => $owner->id]);

    // View Meta Ads (+ unrestricted data access so the demo accounts are visible).
    $viewer = creatorMemberWith($workspace, ['View Meta Ads', 'View All Workspace Data']);
    $this->actingAs($viewer);

    // Reads are allowed and still carry the creator columns.
    $data = $this->getJson(creatorDataUrl($workspace, ['group_by' => 'ad']))
        ->assertOk()->json('rows.data');
    expect(collect($data)->keyBy('id')['1002']['creator_name'])->toBe($owner->name);

    // Writes are not.
    $this->patchJson(creatorUrl($workspace, 1002), ['creator_id' => $viewer->id])
        ->assertForbidden();
    $this->postJson(bulkCreatorUrl($workspace), ['ad_ids' => ['1002'], 'creator_id' => $viewer->id])
        ->assertForbidden();

    expect(Ad::find(1002)->creator_id)->toBe($owner->id);
});

it('lets a member with Manage Meta Ads Accounts tag ads', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    seedCreatorAds($workspace);

    $manager = creatorMemberWith($workspace, [
        'View Meta Ads',
        'Manage Meta Ads Accounts',
        'View All Workspace Data',
    ]);

    $this->actingAs($manager)
        ->patchJson(creatorUrl($workspace, 1002), ['creator_id' => $manager->id])
        ->assertOk()
        ->assertJsonPath('creator.id', $manager->id);

    expect(Ad::find(1002)->creator_id)->toBe($manager->id);
});

it('bulk assign only touches the requested ads', function () {
    ['workspace' => $workspace, 'user' => $owner] = actingAsWorkspaceOwner();
    seedCreatorAds($workspace);

    $this->postJson(bulkCreatorUrl($workspace), [
        'ad_ids' => ['1002'],
        'creator_id' => $owner->id,
    ])->assertOk();

    expect(Ad::find(1002)->creator_id)->toBe($owner->id)
        ->and(Ad::find(2002)->creator_id)->toBeNull();
});

it('bulk clears creators when creator_id is null', function () {
    ['workspace' => $workspace, 'user' => $owner] = actingAsWorkspaceOwner();
    seedCreatorAds($workspace);
    Ad::whereIn('id', [1002, 2002])->update(['creator_id' => $owner->id]);

    $this->postJson(bulkCreatorUrl($workspace), [
        'ad_ids' => ['1002', '2002'],
        'creator_id' => null,
    ])->assertOk();

    expect(Ad::find(1002)->creator_id)->toBeNull()
        ->and(Ad::find(2002)->creator_id)->toBeNull();
});

it('ignores the creator filter for non-ad groupings', function () {
    ['workspace' => $workspace, 'user' => $owner] = actingAsWorkspaceOwner();
    seedCreatorAds($workspace);
    Ad::whereKey(1002)->update(['creator_id' => $owner->id]);

    // Campaign grouping has no per-ad creator — the campaign still shows.
    $rows = $this->getJson(creatorDataUrl($workspace, [
        'group_by' => 'campaign',
        'creator_id' => 'unassigned',
    ]))->assertOk()->json('rows.data');

    expect($rows)->toHaveCount(1);
});

it('exposes the assignable members on the ads manager shell', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    seedCreatorAds($workspace);

    $this->get(route('workspaces.metaads.ads-manager', ['workspace' => $workspace]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('members'));
});
