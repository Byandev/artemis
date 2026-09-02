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

/** A member holding only the tracker's own grant. */
function trackerMember(Workspace $workspace): User
{
    $user = User::factory()->create();

    $role = Role::create(['workspace_id' => $workspace->id, 'name' => 'Tracker '.uniqid()]);

    $permission = Permission::firstOrCreate(
        ['name' => PermissionEnum::ViewNewCreativesTracker->value],
        ['category' => PermissionEnum::ViewNewCreativesTracker->category()],
    );

    DB::table('role_permissions')->insert(['role_id' => $role->id, 'permission_id' => $permission->id]);
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
