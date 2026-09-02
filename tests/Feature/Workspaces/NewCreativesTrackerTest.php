<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\MetaAds\Models\TestingItem;
use Modules\MetaAds\Services\TestingDailyRecordSync;
use Modules\MetaAds\Services\TestingItemResolver;

/**
 * The "Add Testing Item" picker and the save behind it.
 *
 * The picker is the only way things land in meta_ads_testing_items, so what it
 * refuses to offer — paused items, other workspaces' accounts, things already
 * tracked — matters as much as what it lists.
 */

/** A workspace with the S&M pages on, plus one Meta ad account linked to it. */
function trackerContext(): array
{
    ['workspace' => $workspace, 'user' => $owner] = makeWorkspaceWithOwner();

    $workspace->update(['sales_marketing_dashboard_module_enabled' => true]);

    $accountId = random_int(1_000_000, 9_999_999);
    $metaUserId = random_int(1_000_000, 9_999_999);

    DB::table('meta_ads_users')->insert([
        'id' => $metaUserId, 'name' => 'Meta User', 'access_token' => 'token',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('meta_ads_workspace_user')->insert([
        'workspace_id' => $workspace->id, 'meta_ads_user_id' => $metaUserId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('meta_ads_accounts')->insert([
        'id' => $accountId, 'name' => 'Test Account',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('meta_ads_user_account')->insert([
        'meta_ads_user_id' => $metaUserId, 'meta_ads_account_id' => $accountId,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId];
}

function makeCampaign(int $accountId, string $name, string $status = 'ACTIVE', ?string $startTime = null): int
{
    $id = random_int(1_000_000_000, 9_999_999_999);

    DB::table('meta_ads_campaigns')->insert([
        'id' => $id, 'meta_ads_account_id' => $accountId, 'name' => $name,
        'effective_status' => $status, 'start_time' => $startTime,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $id;
}

/** One day of insights for an ad belonging to $campaignId. */
function makeInsight(int $accountId, int $campaignId, string $date, float $spend, float $purchaseValue, ?int $adSetId = null): void
{
    DB::table('meta_ads_insights')->insert([
        'meta_ads_ad_id' => random_int(1_000_000_000, 9_999_999_999),
        'date' => $date,
        'meta_ads_account_id' => $accountId,
        'meta_ads_campaign_id' => $campaignId,
        'meta_ads_set_id' => $adSetId,
        'spend' => $spend,
        'purchase_value' => $purchaseValue,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function makeAdSet(int $accountId, int $campaignId, string $name, string $status = 'ACTIVE'): int
{
    $id = random_int(1_000_000_000, 9_999_999_999);

    DB::table('meta_ads_sets')->insert([
        'id' => $id, 'meta_ads_account_id' => $accountId, 'meta_ads_campaign_id' => $campaignId,
        'name' => $name, 'effective_status' => $status, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $id;
}

/** A member holding the given tracker grants (all four by default). */
function trackerMember(Workspace $workspace, ?array $permissions = null): User
{
    $permissions ??= [
        PermissionEnum::ViewNewCreativesTracker,
        PermissionEnum::CreateNewCreativesTracker,
        PermissionEnum::EditNewCreativesTracker,
        PermissionEnum::DeleteNewCreativesTracker,
    ];

    $user = User::factory()->create();

    $role = Role::create(['workspace_id' => $workspace->id, 'name' => 'Tracker '.uniqid()]);

    foreach ($permissions as $permission) {
        $row = Permission::firstOrCreate(
            ['name' => $permission->value],
            ['category' => $permission->category()],
        );

        DB::table('role_permissions')->insertOrIgnore(['role_id' => $role->id, 'permission_id' => $row->id]);
    }
    $workspace->users()->attach($user->id, ['role_id' => $role->id]);

    return $user;
}

function pickerUrl(Workspace $workspace, array $params = []): string
{
    return "/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/available-items?".http_build_query($params);
}

test('the picker lists active campaigns and leaves paused ones out', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();

    makeCampaign($accountId, 'Live campaign');
    makeCampaign($accountId, 'Paused campaign', 'PAUSED');

    $response = $this->actingAs($owner)
        ->getJson(pickerUrl($workspace, ['type' => 'campaign']))
        ->assertOk();

    $names = collect($response->json('items'))->pluck('name');

    expect($names)->toContain('Live campaign')
        ->and($names)->not->toContain('Paused campaign');
});

test('the picker returns ad sets when asked for them, not campaigns', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();

    $campaignId = makeCampaign($accountId, 'A campaign');
    makeAdSet($accountId, $campaignId, 'An ad set');

    $names = collect($this->actingAs($owner)
        ->getJson(pickerUrl($workspace, ['type' => 'ad_set']))
        ->assertOk()
        ->json('items'))->pluck('name');

    expect($names)->toContain('An ad set')
        ->and($names)->not->toContain('A campaign');
});

test('the search term narrows the list by name', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();

    makeCampaign($accountId, 'Summer sale');
    makeCampaign($accountId, 'Winter sale');

    $names = collect($this->actingAs($owner)
        ->getJson(pickerUrl($workspace, ['type' => 'campaign', 'search' => 'Summer']))
        ->assertOk()
        ->json('items'))->pluck('name');

    expect($names)->toContain('Summer sale')
        ->and($names)->not->toContain('Winter sale');
});

test('the picker never offers something already being tracked', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();

    $trackedId = makeCampaign($accountId, 'Already tracked');
    makeCampaign($accountId, 'Not yet tracked');

    DB::table('meta_ads_testing_items')->insert([
        'workspace_id' => $workspace->id, 'item_type' => 'campaign', 'item_id' => $trackedId,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $names = collect($this->actingAs($owner)
        ->getJson(pickerUrl($workspace, ['type' => 'campaign']))
        ->assertOk()
        ->json('items'))->pluck('name');

    expect($names)->toContain('Not yet tracked')
        ->and($names)->not->toContain('Already tracked');
});

test('another workspace\'s campaigns are not offered', function () {
    ['workspace' => $workspace, 'owner' => $owner] = trackerContext();
    ['accountId' => $otherAccountId] = trackerContext();

    makeCampaign($otherAccountId, 'Someone else\'s campaign');

    $names = collect($this->actingAs($owner)
        ->getJson(pickerUrl($workspace, ['type' => 'campaign']))
        ->assertOk()
        ->json('items'))->pluck('name');

    expect($names)->not->toContain('Someone else\'s campaign');
});

test('picking several items saves them all in one go', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();

    $first = makeCampaign($accountId, 'First');
    $second = makeCampaign($accountId, 'Second');
    $adSet = makeAdSet($accountId, $first, 'An ad set');

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items", [
            'items' => [
                ['item_type' => 'campaign', 'item_id' => (string) $first],
                ['item_type' => 'campaign', 'item_id' => (string) $second],
                ['item_type' => 'ad_set', 'item_id' => (string) $adSet],
            ],
        ])
        ->assertRedirect();

    expect(DB::table('meta_ads_testing_items')->where('workspace_id', $workspace->id)->count())->toBe(3);
});

test('saving something already tracked does not duplicate it', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();

    $campaignId = makeCampaign($accountId, 'Only once');
    $payload = ['items' => [['item_type' => 'campaign', 'item_id' => (string) $campaignId]]];
    $url = "/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items";

    $this->actingAs($owner)->post($url, $payload)->assertRedirect();
    $this->actingAs($owner)->post($url, $payload)->assertRedirect();

    expect(DB::table('meta_ads_testing_items')
        ->where(['workspace_id' => $workspace->id, 'item_type' => 'campaign', 'item_id' => $campaignId])
        ->count())->toBe(1);
});

test('an id the viewer cannot see is dropped rather than saved', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();
    ['accountId' => $otherAccountId] = trackerContext();

    $mine = makeCampaign($accountId, 'Mine');
    $theirs = makeCampaign($otherAccountId, 'Theirs');
    $paused = makeCampaign($accountId, 'Paused', 'PAUSED');

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items", [
            'items' => [
                ['item_type' => 'campaign', 'item_id' => (string) $mine],
                ['item_type' => 'campaign', 'item_id' => (string) $theirs],
                ['item_type' => 'campaign', 'item_id' => (string) $paused],
            ],
        ])
        ->assertRedirect();

    $saved = DB::table('meta_ads_testing_items')->where('workspace_id', $workspace->id)->pluck('item_id');

    expect($saved)->toHaveCount(1)
        ->and((int) $saved->first())->toBe($mine);
});

test('the picker and the save both need the tracker permission', function () {
    ['workspace' => $workspace] = trackerContext();

    $stranger = makeWorkspaceMember($workspace);

    $this->actingAs($stranger)
        ->getJson(pickerUrl($workspace, ['type' => 'campaign']))
        ->assertForbidden();

    $this->actingAs($stranger)
        ->post("/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items", [
            'items' => [['item_type' => 'campaign', 'item_id' => '123']],
        ])
        ->assertForbidden();
});

test('a team-scoped member only sees their own team\'s accounts', function () {
    ['workspace' => $workspace, 'accountId' => $accountId] = trackerContext();

    makeCampaign($accountId, 'On my team\'s account');

    $member = trackerMember($workspace);

    // The grant opens the picker, but ad-account visibility is a separate axis:
    // a scoped member with no team linked to the account sees an empty list
    // rather than everything (visibleTo fails closed).
    $this->actingAs($member)
        ->getJson(pickerUrl($workspace, ['type' => 'campaign']))
        ->assertOk()
        ->assertJsonPath('items', []);

    // Link the account to a team the member belongs to and it shows up.
    $team = Team::factory()->create(['workspace_id' => $workspace->id]);
    $team->members()->attach($member->id);

    DB::table('team_ad_account')->insert([
        'team_id' => $team->id, 'meta_ads_account_id' => $accountId,
        'access_level' => 'view', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($member)
        ->getJson(pickerUrl($workspace, ['type' => 'campaign']))
        ->assertOk()
        ->assertJsonPath('items.0.name', 'On my team\'s account');
});

test('the picker rejects an unknown item type', function () {
    ['workspace' => $workspace, 'owner' => $owner] = trackerContext();

    $this->actingAs($owner)
        ->getJson(pickerUrl($workspace, ['type' => 'ad']))
        ->assertStatus(422);
});

test('day 1 is the item\'s own start date, not the calendar', function () {
    ['workspace' => $workspace, 'accountId' => $accountId] = trackerContext();

    // Started on the 10th, so the 10th is day 1 and the 12th is day 3.
    $campaignId = makeCampaign($accountId, 'Started on the 10th', 'ACTIVE', '2026-08-10 00:00:00');

    makeInsight($accountId, $campaignId, '2026-08-10', 100, 250);
    makeInsight($accountId, $campaignId, '2026-08-12', 50, 200);

    $item = TestingItem::create([
        'workspace_id' => $workspace->id, 'item_type' => 'campaign', 'item_id' => $campaignId,
    ]);

    app(TestingDailyRecordSync::class)->sync(collect([$item]));

    $byDay = DB::table('meta_ads_testing_daily_records')
        ->where('meta_ads_testing_item_id', $item->id)
        ->pluck('day', 'date');

    expect((int) $byDay['2026-08-10'])->toBe(1)
        ->and((int) $byDay['2026-08-12'])->toBe(3);
});

test('two items that started on different dates both begin at day 1', function () {
    ['workspace' => $workspace, 'accountId' => $accountId] = trackerContext();

    $early = makeCampaign($accountId, 'Early', 'ACTIVE', '2026-08-01 00:00:00');
    $late = makeCampaign($accountId, 'Late', 'ACTIVE', '2026-08-20 00:00:00');

    makeInsight($accountId, $early, '2026-08-01', 10, 30);
    makeInsight($accountId, $late, '2026-08-20', 10, 40);

    $items = collect([$early, $late])->map(fn ($id) => TestingItem::create([
        'workspace_id' => $workspace->id, 'item_type' => 'campaign', 'item_id' => $id,
    ]));

    app(TestingDailyRecordSync::class)->sync($items);

    $days = DB::table('meta_ads_testing_daily_records')->pluck('day')->unique()->values();

    expect($days->all())->toBe([1]);
});

test('a day sums its ads and derives roas from purchase value over spend', function () {
    ['workspace' => $workspace, 'accountId' => $accountId] = trackerContext();

    $campaignId = makeCampaign($accountId, 'Two ads', 'ACTIVE', '2026-08-10 00:00:00');

    // Two ads on the same day — the item's day is the sum of both.
    makeInsight($accountId, $campaignId, '2026-08-10', 100, 300);
    makeInsight($accountId, $campaignId, '2026-08-10', 100, 200);

    $item = TestingItem::create([
        'workspace_id' => $workspace->id, 'item_type' => 'campaign', 'item_id' => $campaignId,
    ]);

    app(TestingDailyRecordSync::class)->sync(collect([$item]));

    $record = DB::table('meta_ads_testing_daily_records')
        ->where('meta_ads_testing_item_id', $item->id)
        ->first();

    expect((float) $record->ad_spent)->toBe(200.0)
        ->and((float) $record->sales)->toBe(500.0)
        ->and((float) $record->roas)->toBe(2.5);
});

test('re-running the sync refreshes a day rather than duplicating it', function () {
    ['workspace' => $workspace, 'accountId' => $accountId] = trackerContext();

    $campaignId = makeCampaign($accountId, 'Late attribution', 'ACTIVE', '2026-08-10 00:00:00');
    makeInsight($accountId, $campaignId, '2026-08-10', 100, 100);

    $item = TestingItem::create([
        'workspace_id' => $workspace->id, 'item_type' => 'campaign', 'item_id' => $campaignId,
    ]);

    $sync = app(TestingDailyRecordSync::class);
    $sync->sync(collect([$item]));

    // A late-attributed conversion lands and the day is re-synced.
    makeInsight($accountId, $campaignId, '2026-08-10', 0, 400);
    $sync->sync(collect([$item]));

    $records = DB::table('meta_ads_testing_daily_records')
        ->where('meta_ads_testing_item_id', $item->id)
        ->get();

    expect($records)->toHaveCount(1)
        ->and((float) $records->first()->sales)->toBe(500.0);
});

test('an item with no start time records nothing — there is no day to measure', function () {
    ['workspace' => $workspace, 'accountId' => $accountId] = trackerContext();

    $campaignId = makeCampaign($accountId, 'No start time');
    makeInsight($accountId, $campaignId, '2026-08-10', 100, 300);

    $item = TestingItem::create([
        'workspace_id' => $workspace->id, 'item_type' => 'campaign', 'item_id' => $campaignId,
    ]);

    app(TestingDailyRecordSync::class)->sync(collect([$item]));

    expect(DB::table('meta_ads_testing_daily_records')->where('meta_ads_testing_item_id', $item->id)->count())
        ->toBe(0);
});

test('the test stops at day 7', function () {
    ['workspace' => $workspace, 'accountId' => $accountId] = trackerContext();

    $campaignId = makeCampaign($accountId, 'Long runner', 'ACTIVE', '2026-08-01 00:00:00');

    // Days 1 through 20 of insights; only the first 7 belong to the test.
    foreach (range(0, 19) as $offset) {
        makeInsight($accountId, $campaignId, Carbon::parse('2026-08-01')->addDays($offset)->toDateString(), 10, 20);
    }

    $item = TestingItem::create([
        'workspace_id' => $workspace->id, 'item_type' => 'campaign', 'item_id' => $campaignId,
    ]);

    app(TestingDailyRecordSync::class)->sync(collect([$item]));

    $days = DB::table('meta_ads_testing_daily_records')
        ->where('meta_ads_testing_item_id', $item->id)
        ->pluck('day');

    expect($days)->toHaveCount(TestingDailyRecordSync::MAX_DAY)
        ->and((int) $days->max())->toBe(TestingDailyRecordSync::MAX_DAY);
});

test('days recorded past the window by an earlier run are cleared out', function () {
    ['workspace' => $workspace, 'accountId' => $accountId] = trackerContext();

    $campaignId = makeCampaign($accountId, 'Had a longer window', 'ACTIVE', '2026-08-01 00:00:00');
    makeInsight($accountId, $campaignId, '2026-08-01', 10, 20);

    $item = TestingItem::create([
        'workspace_id' => $workspace->id, 'item_type' => 'campaign', 'item_id' => $campaignId,
    ]);

    // A leftover row from when the window was longer.
    DB::table('meta_ads_testing_daily_records')->insert([
        'meta_ads_testing_item_id' => $item->id, 'date' => '2026-09-05', 'day' => 36,
        'sales' => 1, 'ad_spent' => 1, 'roas' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    app(TestingDailyRecordSync::class)->sync(collect([$item]));

    expect(DB::table('meta_ads_testing_daily_records')->where('day', '>', TestingDailyRecordSync::MAX_DAY)->count())->toBe(0);
});

test('pausing an item stops it accruing days, resuming lets it catch up', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();

    $campaignId = makeCampaign($accountId, 'Pausable', 'ACTIVE', '2026-08-01 00:00:00');
    makeInsight($accountId, $campaignId, '2026-08-01', 10, 20);

    $item = TestingItem::create([
        'workspace_id' => $workspace->id, 'item_type' => 'campaign', 'item_id' => $campaignId,
    ]);

    // Day 1 is on the board before the pause, so the pause is preserving
    // history rather than there being none to begin with.
    app(TestingDailyRecordSync::class)->sync(collect([$item]));

    $url = "/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items/{$item->id}/pause";

    $this->actingAs($owner)->patch($url)->assertRedirect();
    expect($item->fresh()->paused_at)->not->toBeNull();

    // A paused item is skipped by the scheduled sync, so a new day of insights
    // does not land while it is paused.
    makeInsight($accountId, $campaignId, '2026-08-02', 10, 40);
    app(TestingDailyRecordSync::class)->all();

    expect(DB::table('meta_ads_testing_daily_records')->where('meta_ads_testing_item_id', $item->id)->count())->toBe(1);

    // Resuming catches it up on what it missed.
    $this->actingAs($owner)->patch($url)->assertRedirect();

    expect($item->fresh()->paused_at)->toBeNull()
        ->and(DB::table('meta_ads_testing_daily_records')->where('meta_ads_testing_item_id', $item->id)->count())->toBe(2);
});

test('pausing another workspace\'s item is refused', function () {
    ['workspace' => $mine, 'owner' => $owner] = trackerContext();
    ['workspace' => $theirs, 'accountId' => $theirAccountId] = trackerContext();

    $campaignId = makeCampaign($theirAccountId, 'Not mine', 'ACTIVE', '2026-08-01 00:00:00');
    $item = TestingItem::create([
        'workspace_id' => $theirs->id, 'item_type' => 'campaign', 'item_id' => $campaignId,
    ]);

    $this->actingAs($owner)
        ->patch("/workspaces/{$mine->slug}/sales-marketing/new-creatives-tracker/items/{$item->id}/pause")
        ->assertNotFound();

    expect($item->fresh()->paused_at)->toBeNull();
});

test('adding an item stamps the ad account it runs on', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();

    $campaignId = makeCampaign($accountId, 'Has an account', 'ACTIVE', '2026-08-01 00:00:00');

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items", [
            'items' => [['item_type' => 'campaign', 'item_id' => (string) $campaignId]],
        ])
        ->assertRedirect();

    expect((int) DB::table('meta_ads_testing_items')->value('meta_ads_account_id'))->toBe($accountId);
});

test('the product resolves through page and shop', function () {
    ['workspace' => $workspace, 'accountId' => $accountId] = trackerContext();

    $product = DB::table('products')->insertGetId([
        'workspace_id' => $workspace->id, 'owner_id' => User::factory()->create()->id,
        'name' => 'Nerve Cream', 'title' => 'Nerve Cream', 'code' => 'NC-1',
        'category' => 'Skincare', 'status' => 'Testing', 'description' => 'x',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $shopId = random_int(100_000, 999_999);
    DB::table('shops')->insert([
        'id' => $shopId, 'workspace_id' => $workspace->id, 'product_id' => $product,
        'name' => 'A shop', 'created_at' => now(), 'updated_at' => now(),
    ]);

    // A Pancake page's id is the Facebook page id, which is what makes the
    // meta_page_id -> page hop a direct lookup.
    $fbPageId = random_int(1_000_000_000, 9_999_999_999);
    DB::table('pages')->insert([
        'id' => $fbPageId, 'workspace_id' => $workspace->id, 'shop_id' => $shopId,
        'owner_id' => User::factory()->create()->id,
        'name' => 'A page', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $campaignId = makeCampaign($accountId, 'Sells the cream', 'ACTIVE', '2026-08-01 00:00:00');
    $adSetId = makeAdSet($accountId, $campaignId, 'Its ad set');
    DB::table('meta_ads_sets')->where('id', $adSetId)->update(['meta_page_id' => $fbPageId]);

    $items = collect(['ad_set' => $adSetId, 'campaign' => $campaignId])
        ->map(fn ($id, $type) => TestingItem::create([
            'workspace_id' => $workspace->id, 'item_type' => $type, 'item_id' => $id,
        ]));

    app(TestingItemResolver::class)->resolve($items);

    // The ad set carries the page directly; the campaign borrows it from the
    // ad set, so both land on the same product.
    expect($items->map(fn ($i) => (int) $i->fresh()->product_id)->all())
        ->toBe(['ad_set' => $product, 'campaign' => $product]);
});

test('an unresolvable product leaves the column null rather than failing', function () {
    ['workspace' => $workspace, 'accountId' => $accountId] = trackerContext();

    // No meta_page_id on the ad set, so there is no page to walk to.
    $campaignId = makeCampaign($accountId, 'No page', 'ACTIVE', '2026-08-01 00:00:00');
    $item = TestingItem::create([
        'workspace_id' => $workspace->id, 'item_type' => 'campaign', 'item_id' => $campaignId,
    ]);

    app(TestingItemResolver::class)->resolve(collect([$item]));

    expect($item->fresh()->product_id)->toBeNull()
        ->and((int) $item->fresh()->meta_ads_account_id)->toBe($accountId);
});

test('adding an item backfills its history immediately', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();

    $campaignId = makeCampaign($accountId, 'Has history', 'ACTIVE', '2026-08-10 00:00:00');
    makeInsight($accountId, $campaignId, '2026-08-10', 100, 400);
    makeInsight($accountId, $campaignId, '2026-08-11', 100, 100);

    $this->actingAs($owner)
        ->post("/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items", [
            'items' => [['item_type' => 'campaign', 'item_id' => (string) $campaignId]],
        ])
        ->assertRedirect();

    // The picker save alone should leave the tracker already populated.
    expect(DB::table('meta_ads_testing_daily_records')->count())->toBe(2);
});

test('the intern decision and finance status save', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();

    $campaignId = makeCampaign($accountId, 'Decidable', 'ACTIVE', '2026-08-01 00:00:00');
    $item = TestingItem::create([
        'workspace_id' => $workspace->id, 'item_type' => 'campaign', 'item_id' => $campaignId,
    ]);

    $url = "/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items/{$item->id}";

    // Both start empty — a running test has not been decided yet.
    expect($item->intern_decision)->toBeNull()
        ->and($item->finance_status)->toBeNull();

    $this->actingAs($owner)->patch($url, ['intern_decision' => 'split_50_50'])->assertRedirect();
    $this->actingAs($owner)->patch($url, ['finance_status' => 'collected'])->assertRedirect();

    expect($item->fresh()->intern_decision)->toBe('split_50_50')
        ->and($item->fresh()->finance_status)->toBe('collected');
});

test('sending one status leaves the other alone', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();

    $campaignId = makeCampaign($accountId, 'Half decided', 'ACTIVE', '2026-08-01 00:00:00');
    $item = TestingItem::create([
        'workspace_id' => $workspace->id, 'item_type' => 'campaign', 'item_id' => $campaignId,
        'intern_decision' => 'scale', 'finance_status' => 'pending',
    ]);

    $this->actingAs($owner)
        ->patch("/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items/{$item->id}", [
            'finance_status' => 'collected',
        ])
        ->assertRedirect();

    expect($item->fresh()->intern_decision)->toBe('scale')
        ->and($item->fresh()->finance_status)->toBe('collected');
});

test('a status can be cleared back to undecided', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();

    $campaignId = makeCampaign($accountId, 'Reversible', 'ACTIVE', '2026-08-01 00:00:00');
    $item = TestingItem::create([
        'workspace_id' => $workspace->id, 'item_type' => 'campaign', 'item_id' => $campaignId,
        'intern_decision' => 'killed',
    ]);

    $this->actingAs($owner)
        ->patch("/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items/{$item->id}", [
            'intern_decision' => null,
        ])
        ->assertRedirect();

    expect($item->fresh()->intern_decision)->toBeNull();
});

test('an unknown status value is rejected', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();

    $campaignId = makeCampaign($accountId, 'Strict', 'ACTIVE', '2026-08-01 00:00:00');
    $item = TestingItem::create([
        'workspace_id' => $workspace->id, 'item_type' => 'campaign', 'item_id' => $campaignId,
    ]);

    $this->actingAs($owner)
        ->patchJson("/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items/{$item->id}", [
            'intern_decision' => 'maybe',
        ])
        ->assertStatus(422);

    expect($item->fresh()->intern_decision)->toBeNull();
});

test('another workspace\'s item cannot be decided', function () {
    ['workspace' => $mine, 'owner' => $owner] = trackerContext();
    ['workspace' => $theirs, 'accountId' => $theirAccountId] = trackerContext();

    $campaignId = makeCampaign($theirAccountId, 'Not mine', 'ACTIVE', '2026-08-01 00:00:00');
    $item = TestingItem::create([
        'workspace_id' => $theirs->id, 'item_type' => 'campaign', 'item_id' => $campaignId,
    ]);

    $this->actingAs($owner)
        ->patch("/workspaces/{$mine->slug}/sales-marketing/new-creatives-tracker/items/{$item->id}", [
            'intern_decision' => 'scale',
        ])
        ->assertNotFound();

    expect($item->fresh()->intern_decision)->toBeNull();
});

test('the sync never touches a decision', function () {
    ['workspace' => $workspace, 'accountId' => $accountId] = trackerContext();

    $campaignId = makeCampaign($accountId, 'Decided and synced', 'ACTIVE', '2026-08-01 00:00:00');
    makeInsight($accountId, $campaignId, '2026-08-01', 10, 40);

    $item = TestingItem::create([
        'workspace_id' => $workspace->id, 'item_type' => 'campaign', 'item_id' => $campaignId,
        'intern_decision' => 'scale', 'finance_status' => 'collected',
    ]);

    // These are human calls, not derived figures — a metrics sync must leave
    // them exactly as they were.
    app(TestingItemResolver::class)->resolve(collect([$item]));
    app(TestingDailyRecordSync::class)->sync(collect([$item]));

    expect($item->fresh()->intern_decision)->toBe('scale')
        ->and($item->fresh()->finance_status)->toBe('collected');
});

/** The tracker page URL with an optional filter query. */
function trackerUrl(Workspace $workspace, array $query = []): string
{
    $url = "/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker";

    return $query ? $url.'?'.http_build_query($query) : $url;
}

/** Track $name as a campaign in $workspace, optionally with a start date. */
function trackCampaign(Workspace $workspace, int $accountId, string $name, ?string $startTime = null): TestingItem
{
    return TestingItem::create([
        'workspace_id' => $workspace->id,
        'item_type' => 'campaign',
        'item_id' => makeCampaign($accountId, $name, 'ACTIVE', $startTime),
        'meta_ads_account_id' => $accountId,
    ]);
}

/** The item names the page rendered, in order. */
function renderedNames($response): array
{
    return collect($response->viewData('page')['props']['items']['data'])
        ->pluck('name')
        ->all();
}

test('the search narrows the list by campaign name', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();

    trackCampaign($workspace, $accountId, 'Summer launch');
    trackCampaign($workspace, $accountId, 'Winter launch');

    $names = renderedNames($this->actingAs($owner)->get(trackerUrl($workspace, ['search' => 'Summer']))->assertOk());

    expect($names)->toContain('Summer launch')
        ->and($names)->not->toContain('Winter launch');
});

test('the ad account filter narrows the list', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $mine] = trackerContext();
    ['accountId' => $other] = trackerContext();

    trackCampaign($workspace, $mine, 'On my account');
    trackCampaign($workspace, $other, 'On the other account');

    $names = renderedNames($this->actingAs($owner)->get(trackerUrl($workspace, ['account' => $mine]))->assertOk());

    expect($names)->toBe(['On my account']);
});

test('the type filter separates campaigns from ad sets', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();

    $campaign = trackCampaign($workspace, $accountId, 'A campaign');
    TestingItem::create([
        'workspace_id' => $workspace->id, 'item_type' => 'ad_set',
        'item_id' => makeAdSet($accountId, (int) $campaign->item_id, 'An ad set'),
        'meta_ads_account_id' => $accountId,
    ]);

    expect(renderedNames($this->actingAs($owner)->get(trackerUrl($workspace, ['type' => 'campaign']))))
        ->toBe(['A campaign'])
        ->and(renderedNames($this->actingAs($owner)->get(trackerUrl($workspace, ['type' => 'ad_set']))))
        ->toBe(['An ad set']);
});

test('the start date range filters on when the item started', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();

    trackCampaign($workspace, $accountId, 'Started in July', '2026-07-15 00:00:00');
    trackCampaign($workspace, $accountId, 'Started in August', '2026-08-15 00:00:00');

    $names = renderedNames($this->actingAs($owner)->get(trackerUrl($workspace, [
        'start_from' => '2026-08-01', 'start_to' => '2026-08-31',
    ]))->assertOk());

    expect($names)->toBe(['Started in August']);
});

test('filters combine rather than replace each other', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $mine] = trackerContext();
    ['accountId' => $other] = trackerContext();

    trackCampaign($workspace, $mine, 'Launch A', '2026-08-10 00:00:00');
    trackCampaign($workspace, $other, 'Launch B', '2026-08-10 00:00:00');
    trackCampaign($workspace, $mine, 'Launch C', '2026-07-10 00:00:00');

    // Right account AND right window AND matching name — only Launch A.
    $names = renderedNames($this->actingAs($owner)->get(trackerUrl($workspace, [
        'search' => 'Launch', 'account' => $mine, 'start_from' => '2026-08-01',
    ]))->assertOk());

    expect($names)->toBe(['Launch A']);
});

test('the list paginates and the second page carries the rest', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();

    foreach (range(1, 7) as $n) {
        trackCampaign($workspace, $accountId, "Campaign {$n}");
    }

    $first = $this->actingAs($owner)->get(trackerUrl($workspace, ['per_page' => 5]))->assertOk();
    $props = $first->viewData('page')['props']['items'];

    expect($props['data'])->toHaveCount(5)
        ->and($props['total'])->toBe(7)
        ->and($props['last_page'])->toBe(2);

    $second = $this->actingAs($owner)->get(trackerUrl($workspace, ['per_page' => 5, 'page' => 2]))->assertOk();

    expect($second->viewData('page')['props']['items']['data'])->toHaveCount(2);
});

test('paging keeps the filters applied', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $mine] = trackerContext();
    ['accountId' => $other] = trackerContext();

    foreach (range(1, 6) as $n) {
        trackCampaign($workspace, $mine, "Mine {$n}");
    }
    trackCampaign($workspace, $other, 'Not mine');

    // Page 2 of the filtered set must not leak the other account's item in.
    $names = renderedNames($this->actingAs($owner)->get(trackerUrl($workspace, [
        'account' => $mine, 'per_page' => 5, 'page' => 2,
    ]))->assertOk());

    expect($names)->toHaveCount(1)
        ->and($names)->not->toContain('Not mine');
});

test('the account filter only offers accounts that are actually tracked', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $used] = trackerContext();
    ['accountId' => $unused] = trackerContext();

    trackCampaign($workspace, $used, 'Tracked');

    $offered = collect($this->actingAs($owner)->get(trackerUrl($workspace))->assertOk()
        ->viewData('page')['props']['accounts'])->pluck('id');

    expect($offered)->toContain((string) $used)
        ->and($offered)->not->toContain((string) $unused);
});

test('the filters come back on the page so a refresh keeps them', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();

    trackCampaign($workspace, $accountId, 'Anything', '2026-08-10 00:00:00');

    $filters = $this->actingAs($owner)->get(trackerUrl($workspace, [
        'search' => 'Any', 'type' => 'campaign', 'start_from' => '2026-08-01',
    ]))->assertOk()->viewData('page')['props']['filters'];

    expect($filters['search'])->toBe('Any')
        ->and($filters['type'])->toBe('campaign')
        ->and($filters['start_from'])->toBe('2026-08-01');
});

test('an invalid filter value is rejected', function () {
    ['workspace' => $workspace, 'owner' => $owner] = trackerContext();

    $this->actingAs($owner)
        ->getJson(trackerUrl($workspace, ['type' => 'banana']))
        ->assertStatus(422);
});

/** Post a manual item, returning the response. */
function addManual(Workspace $workspace, array $payload = [])
{
    return test()->post(
        "/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items/manual",
        array_merge([
            'item_type' => 'campaign',
            'name' => 'Manual entry',
            'start_date' => '2026-08-01',
        ], $payload),
    );
}

/** The day-cell endpoint for one item and day. */
function dayUrl(Workspace $workspace, TestingItem $item, int $day): string
{
    return "/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items/{$item->id}/days/{$day}";
}

/** A manual item ready to have its days typed in. */
function manualItem(Workspace $workspace, string $start = '2026-08-01'): TestingItem
{
    return TestingItem::create([
        'workspace_id' => $workspace->id, 'source' => 'manual', 'item_type' => 'campaign',
        'item_id' => null, 'name' => 'Typed', 'start_date' => $start,
    ]);
}

test('a manual item is created with its own name, account and page', function () {
    ['workspace' => $workspace, 'owner' => $owner] = trackerContext();

    $this->actingAs($owner);

    addManual($workspace, [
        'item_type' => 'ad_set',
        'name' => 'Hand-entered ad set',
        'account_name' => 'Some Account',
        'page_name' => 'Some Page',
        'start_date' => '2026-08-10',
    ])->assertRedirect();

    $item = TestingItem::first();

    expect($item->source)->toBe('manual')
        ->and($item->item_id)->toBeNull()
        ->and($item->name)->toBe('Hand-entered ad set')
        ->and($item->account_name)->toBe('Some Account')
        ->and($item->page_name)->toBe('Some Page')
        ->and($item->start_date->toDateString())->toBe('2026-08-10');
});

test('a manual item needs a name', function () {
    ['workspace' => $workspace, 'owner' => $owner] = trackerContext();

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items/manual", [
            'item_type' => 'campaign',
        ])
        ->assertStatus(422);

    expect(TestingItem::count())->toBe(0);
});

test('several manual items can coexist despite the unique key', function () {
    ['workspace' => $workspace, 'owner' => $owner] = trackerContext();

    $this->actingAs($owner);

    // All three have a null item_id — the unique index tolerates that.
    addManual($workspace, ['name' => 'First'])->assertRedirect();
    addManual($workspace, ['name' => 'Second'])->assertRedirect();
    addManual($workspace, ['name' => 'Third'])->assertRedirect();

    expect(TestingItem::count())->toBe(3);
});

test('the sync leaves manual items alone', function () {
    ['workspace' => $workspace, 'accountId' => $accountId] = trackerContext();

    $manual = TestingItem::create([
        'workspace_id' => $workspace->id, 'source' => 'manual', 'item_type' => 'campaign',
        'item_id' => null, 'name' => 'Manual', 'account_name' => 'Typed in',
        'start_date' => '2026-08-01',
    ]);

    // An insight row that would match if item_id were read as a campaign id.
    makeInsight($accountId, (int) $manual->id, '2026-08-01', 10, 40);

    app(TestingDailyRecordSync::class)->all();
    app(TestingItemResolver::class)->resolve(collect([$manual]));

    expect(DB::table('meta_ads_testing_daily_records')->count())->toBe(0)
        ->and($manual->fresh()->account_name)->toBe('Typed in')
        ->and($manual->fresh()->meta_ads_account_id)->toBeNull();
});

test('the page shows a manual item using its own fields', function () {
    ['workspace' => $workspace, 'owner' => $owner] = trackerContext();

    TestingItem::create([
        'workspace_id' => $workspace->id, 'source' => 'manual', 'item_type' => 'campaign',
        'item_id' => null, 'name' => 'Typed campaign', 'account_name' => 'Typed account',
        'page_name' => 'Typed page', 'start_date' => '2026-08-05',
    ]);

    $row = $this->actingAs($owner)->get(trackerUrl($workspace))->assertOk()
        ->viewData('page')['props']['items']['data'][0];

    expect($row['name'])->toBe('Typed campaign')
        ->and($row['source'])->toBe('manual')
        ->and($row['account_name'])->toBe('Typed account')
        ->and($row['page_name'])->toBe('Typed page')
        ->and($row['start_date'])->toBe('2026-08-05');
});

test('the source filter separates manual from synced', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();

    trackCampaign($workspace, $accountId, 'Synced one');
    TestingItem::create([
        'workspace_id' => $workspace->id, 'source' => 'manual', 'item_type' => 'campaign',
        'item_id' => null, 'name' => 'Manual one',
    ]);

    expect(renderedNames($this->actingAs($owner)->get(trackerUrl($workspace, ['source' => 'manual']))))
        ->toBe(['Manual one'])
        ->and(renderedNames($this->actingAs($owner)->get(trackerUrl($workspace, ['source' => 'meta']))))
        ->toBe(['Synced one']);
});

test('search and date filters reach manual items too', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();

    trackCampaign($workspace, $accountId, 'Synced launch', '2026-08-10 00:00:00');
    TestingItem::create([
        'workspace_id' => $workspace->id, 'source' => 'manual', 'item_type' => 'campaign',
        'item_id' => null, 'name' => 'Manual launch', 'start_date' => '2026-08-12',
    ]);
    TestingItem::create([
        'workspace_id' => $workspace->id, 'source' => 'manual', 'item_type' => 'campaign',
        'item_id' => null, 'name' => 'Manual launch old', 'start_date' => '2026-07-01',
    ]);

    // A manual row is matched on its own name and start_date, not through a
    // Meta table it has no row in.
    $names = renderedNames($this->actingAs($owner)->get(trackerUrl($workspace, [
        'search' => 'launch', 'start_from' => '2026-08-01',
    ]))->assertOk());

    expect($names)->toContain('Manual launch')
        ->and($names)->toContain('Synced launch')
        ->and($names)->not->toContain('Manual launch old');
});

test('a manual item records its product as text', function () {
    ['workspace' => $workspace, 'owner' => $owner] = trackerContext();

    $this->actingAs($owner);
    addManual($workspace, ['product_name' => 'Nerve Cream'])->assertRedirect();

    expect(TestingItem::first()->product_name)->toBe('Nerve Cream');

    $row = $this->actingAs($owner)->get(trackerUrl($workspace))
        ->viewData('page')['props']['items']['data'][0];

    expect($row['product'])->toBe('Nerve Cream');
});

test('a manual item needs a start date', function () {
    ['workspace' => $workspace, 'owner' => $owner] = trackerContext();

    $this->actingAs($owner)
        ->postJson("/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items/manual", [
            'item_type' => 'campaign', 'name' => 'No start',
        ])
        ->assertStatus(422);
});

test('a day cell can be typed in, and roas follows from it', function () {
    ['workspace' => $workspace, 'owner' => $owner] = trackerContext();

    $item = manualItem($workspace);

    $this->actingAs($owner)->patch(dayUrl($workspace, $item, 1), [
        'sales' => 1000, 'ad_spent' => 250,
    ])->assertRedirect();

    $record = DB::table('meta_ads_testing_daily_records')->first();

    expect((int) $record->day)->toBe(1)
        // Day 1 is the start date itself.
        ->and($record->date)->toBe('2026-08-01')
        ->and((float) $record->sales)->toBe(1000.0)
        ->and((float) $record->ad_spent)->toBe(250.0)
        // Never typed — derived from the two figures that were.
        ->and((float) $record->roas)->toBe(4.0);
});

test('a later day lands on the right date', function () {
    ['workspace' => $workspace, 'owner' => $owner] = trackerContext();

    $item = manualItem($workspace, '2026-08-01');

    $this->actingAs($owner)->patch(dayUrl($workspace, $item, 5), ['sales' => 10, 'ad_spent' => 5])
        ->assertRedirect();

    $record = DB::table('meta_ads_testing_daily_records')->first();

    expect((int) $record->day)->toBe(5)
        ->and($record->date)->toBe('2026-08-05');
});

test('typing a day twice updates it rather than adding another', function () {
    ['workspace' => $workspace, 'owner' => $owner] = trackerContext();

    $item = manualItem($workspace);

    $this->actingAs($owner)->patch(dayUrl($workspace, $item, 1), ['sales' => 100, 'ad_spent' => 50]);
    $this->actingAs($owner)->patch(dayUrl($workspace, $item, 1), ['sales' => 900]);

    $records = DB::table('meta_ads_testing_daily_records')->get();

    expect($records)->toHaveCount(1)
        // Sending one field leaves the other in place, and roas re-derives.
        ->and((float) $records->first()->sales)->toBe(900.0)
        ->and((float) $records->first()->ad_spent)->toBe(50.0)
        ->and((float) $records->first()->roas)->toBe(18.0);
});

test('a synced item refuses typed figures', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();

    $item = trackCampaign($workspace, $accountId, 'Synced', '2026-08-01 00:00:00');

    // Its days are rebuilt from insights hourly, so typing over them would be
    // lost within the hour — refused rather than silently discarded later.
    $this->actingAs($owner)->patch(dayUrl($workspace, $item, 1), ['sales' => 100])
        ->assertForbidden();

    expect(DB::table('meta_ads_testing_daily_records')->count())->toBe(0);
});

test('a day outside the window is refused', function () {
    ['workspace' => $workspace, 'owner' => $owner] = trackerContext();

    $item = manualItem($workspace);

    $this->actingAs($owner)->patch(dayUrl($workspace, $item, 8), ['sales' => 1])->assertNotFound();
    $this->actingAs($owner)->patch(dayUrl($workspace, $item, 0), ['sales' => 1])->assertNotFound();
});

test('another workspace\'s day cannot be typed', function () {
    ['workspace' => $mine, 'owner' => $owner] = trackerContext();
    ['workspace' => $theirs] = trackerContext();

    $item = manualItem($theirs);

    $this->actingAs($owner)->patch(dayUrl($mine, $item, 1), ['sales' => 100])->assertNotFound();

    expect(DB::table('meta_ads_testing_daily_records')->count())->toBe(0);
});

test('typed days survive a sync run', function () {
    ['workspace' => $workspace, 'owner' => $owner] = trackerContext();

    $item = manualItem($workspace);
    $this->actingAs($owner)->patch(dayUrl($workspace, $item, 1), ['sales' => 500, 'ad_spent' => 100]);

    app(TestingDailyRecordSync::class)->all();

    // The sync skips manual items entirely, so nothing it does can clear these.
    expect((float) DB::table('meta_ads_testing_daily_records')->first()->sales)->toBe(500.0);
});

test('a manual item\'s start date can be set from the row', function () {
    ['workspace' => $workspace, 'owner' => $owner] = trackerContext();

    // A row saved before a start date was required is recoverable rather than
    // stuck: set the date, then its days become typeable.
    $item = TestingItem::create([
        'workspace_id' => $workspace->id, 'source' => 'manual', 'item_type' => 'campaign',
        'item_id' => null, 'name' => 'No start yet',
    ]);

    $url = "/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items/{$item->id}";

    $this->actingAs($owner)->patch($url, ['start_date' => '2026-08-20'])->assertRedirect();

    expect($item->fresh()->start_date->toDateString())->toBe('2026-08-20');

    $this->actingAs($owner)->patch(dayUrl($workspace, $item, 1), ['sales' => 300, 'ad_spent' => 100])
        ->assertRedirect();

    expect(DB::table('meta_ads_testing_daily_records')->first()->date)->toBe('2026-08-20');
});

test('a synced item refuses a typed start date', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();

    $item = trackCampaign($workspace, $accountId, 'Synced', '2026-08-01 00:00:00');

    $this->actingAs($owner)
        ->patch("/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items/{$item->id}", [
            'start_date' => '2026-09-01',
        ])
        ->assertForbidden();

    expect($item->fresh()->start_date)->toBeNull();
});

test('typing a day on an item with no start date is refused, not fatal', function () {
    ['workspace' => $workspace, 'owner' => $owner] = trackerContext();

    $item = TestingItem::create([
        'workspace_id' => $workspace->id, 'source' => 'manual', 'item_type' => 'campaign',
        'item_id' => null, 'name' => 'No start',
    ]);

    $this->actingAs($owner)
        ->patchJson(dayUrl($workspace, $item, 1), ['sales' => 100])
        ->assertStatus(422);

    expect(DB::table('meta_ads_testing_daily_records')->count())->toBe(0);
});

test('an item can be removed, taking its recorded days with it', function () {
    ['workspace' => $workspace, 'owner' => $owner] = trackerContext();

    $item = manualItem($workspace);
    $this->actingAs($owner)->patch(dayUrl($workspace, $item, 1), ['sales' => 100, 'ad_spent' => 25]);

    expect(DB::table('meta_ads_testing_daily_records')->count())->toBe(1);

    $this->actingAs($owner)
        ->delete("/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items/{$item->id}")
        ->assertRedirect();

    // The daily records go with it — the foreign key cascades.
    expect(TestingItem::count())->toBe(0)
        ->and(DB::table('meta_ads_testing_daily_records')->count())->toBe(0);
});

test('a synced item can be removed too, leaving the campaign alone', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();

    $item = trackCampaign($workspace, $accountId, 'Synced', '2026-08-01 00:00:00');

    $this->actingAs($owner)
        ->delete("/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items/{$item->id}")
        ->assertRedirect();

    expect(TestingItem::count())->toBe(0)
        // Only the tracker row goes; the campaign itself is untouched.
        ->and(DB::table('meta_ads_campaigns')->where('id', $item->item_id)->exists())->toBeTrue();
});

test('another workspace\'s item cannot be removed', function () {
    ['workspace' => $mine, 'owner' => $owner] = trackerContext();
    ['workspace' => $theirs] = trackerContext();

    $item = manualItem($theirs);

    $this->actingAs($owner)
        ->delete("/workspaces/{$mine->slug}/sales-marketing/new-creatives-tracker/items/{$item->id}")
        ->assertNotFound();

    expect(TestingItem::count())->toBe(1);
});

test('a manual item can be edited', function () {
    ['workspace' => $workspace, 'owner' => $owner] = trackerContext();

    $item = manualItem($workspace);

    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items/{$item->id}", [
            'item_type' => 'ad_set',
            'name' => 'Renamed',
            'account_name' => 'New account',
            'page_name' => 'New page',
            'product_name' => 'New product',
            'start_date' => '2026-08-01',
        ])
        ->assertRedirect();

    $item->refresh();

    expect($item->name)->toBe('Renamed')
        ->and($item->item_type)->toBe('ad_set')
        ->and($item->account_name)->toBe('New account')
        ->and($item->page_name)->toBe('New page')
        ->and($item->product_name)->toBe('New product');
});

test('moving the start date re-dates the days already recorded', function () {
    ['workspace' => $workspace, 'owner' => $owner] = trackerContext();

    $item = manualItem($workspace, '2026-08-01');

    $this->actingAs($owner)->patch(dayUrl($workspace, $item, 1), ['sales' => 100, 'ad_spent' => 20]);
    $this->actingAs($owner)->patch(dayUrl($workspace, $item, 3), ['sales' => 300, 'ad_spent' => 60]);

    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items/{$item->id}", [
            'item_type' => 'campaign', 'name' => 'Typed', 'start_date' => '2026-09-10',
        ])
        ->assertRedirect();

    // Day 1 follows the new start date, day 3 stays two days after it — the
    // numbers keep the day they were entered against.
    $byDay = DB::table('meta_ads_testing_daily_records')
        ->where('meta_ads_testing_item_id', $item->id)
        ->pluck('date', 'day');

    expect($byDay[1])->toBe('2026-09-10')
        ->and($byDay[3])->toBe('2026-09-12');
});

test('a synced item refuses an edit', function () {
    ['workspace' => $workspace, 'owner' => $owner, 'accountId' => $accountId] = trackerContext();

    $item = trackCampaign($workspace, $accountId, 'Synced', '2026-08-01 00:00:00');

    $this->actingAs($owner)
        ->put("/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items/{$item->id}", [
            'item_type' => 'campaign', 'name' => 'Hijacked', 'start_date' => '2026-08-01',
        ])
        ->assertForbidden();

    expect($item->fresh()->name)->toBeNull();
});

/** Each write endpoint, with the grant that opens it. */
dataset('tracker_writes', [
    'add from picker' => ['post', 'items', PermissionEnum::CreateNewCreativesTracker],
    'add manual' => ['post', 'items/manual', PermissionEnum::CreateNewCreativesTracker],
]);

test('viewing does not grant adding', function () {
    ['workspace' => $workspace, 'accountId' => $accountId] = trackerContext();

    $viewer = trackerMember($workspace, [PermissionEnum::ViewNewCreativesTracker]);
    $campaignId = makeCampaign($accountId, 'Addable', 'ACTIVE', '2026-08-01 00:00:00');

    // The page opens...
    $this->actingAs($viewer)->get(trackerUrl($workspace))->assertOk();

    // ...but nothing can be added through it.
    $this->actingAs($viewer)
        ->post("/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items", [
            'items' => [['item_type' => 'campaign', 'item_id' => (string) $campaignId]],
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post("/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items/manual", [
            'item_type' => 'campaign', 'name' => 'Nope', 'start_date' => '2026-08-01',
        ])
        ->assertForbidden();

    expect(TestingItem::count())->toBe(0);
});

test('adding does not grant editing or removing', function () {
    ['workspace' => $workspace] = trackerContext();

    $adder = trackerMember($workspace, [
        PermissionEnum::ViewNewCreativesTracker,
        PermissionEnum::CreateNewCreativesTracker,
    ]);

    $item = manualItem($workspace);
    $base = "/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items/{$item->id}";

    $this->actingAs($adder)->patch($base, ['intern_decision' => 'scale'])->assertForbidden();
    $this->actingAs($adder)->patch("{$base}/pause")->assertForbidden();
    $this->actingAs($adder)->patch("{$base}/days/1", ['sales' => 10])->assertForbidden();
    $this->actingAs($adder)->put($base, [
        'item_type' => 'campaign', 'name' => 'x', 'start_date' => '2026-08-01',
    ])->assertForbidden();
    $this->actingAs($adder)->delete($base)->assertForbidden();

    expect($item->fresh()->intern_decision)->toBeNull()
        ->and(TestingItem::count())->toBe(1);
});

test('editing does not grant removing', function () {
    ['workspace' => $workspace] = trackerContext();

    $editor = trackerMember($workspace, [
        PermissionEnum::ViewNewCreativesTracker,
        PermissionEnum::EditNewCreativesTracker,
    ]);

    $item = manualItem($workspace);
    $base = "/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items/{$item->id}";

    $this->actingAs($editor)->patch($base, ['finance_status' => 'collected'])->assertRedirect();
    $this->actingAs($editor)->delete($base)->assertForbidden();

    expect(TestingItem::count())->toBe(1);
});

test('removing does not grant editing', function () {
    ['workspace' => $workspace] = trackerContext();

    $remover = trackerMember($workspace, [
        PermissionEnum::ViewNewCreativesTracker,
        PermissionEnum::DeleteNewCreativesTracker,
    ]);

    $item = manualItem($workspace);
    $base = "/workspaces/{$workspace->slug}/sales-marketing/new-creatives-tracker/items/{$item->id}";

    $this->actingAs($remover)->patch($base, ['finance_status' => 'collected'])->assertForbidden();
    $this->actingAs($remover)->delete($base)->assertRedirect();

    expect(TestingItem::count())->toBe(0);
});

test('the page tells the client which grants it holds', function () {
    ['workspace' => $workspace] = trackerContext();

    $can = $this->actingAs(trackerMember($workspace, [
        PermissionEnum::ViewNewCreativesTracker,
        PermissionEnum::EditNewCreativesTracker,
    ]))->get(trackerUrl($workspace))->assertOk()->viewData('page')['props']['can'];

    // The buttons a viewer cannot use are never drawn, so the page has to know.
    expect($can['create'])->toBeFalse()
        ->and($can['edit'])->toBeTrue()
        ->and($can['delete'])->toBeFalse();
});

test('the module switch hides all four tracker grants', function () {
    ['workspace' => $workspace] = trackerContext();

    $workspace->update(['sales_marketing_dashboard_module_enabled' => false]);

    expect($workspace->fresh()->hiddenPermissionNames())->toContain(
        PermissionEnum::ViewNewCreativesTracker->value,
        PermissionEnum::CreateNewCreativesTracker->value,
        PermissionEnum::EditNewCreativesTracker->value,
        PermissionEnum::DeleteNewCreativesTracker->value,
    );
});
