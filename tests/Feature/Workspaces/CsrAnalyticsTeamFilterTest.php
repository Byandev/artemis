<?php

use App\Enums\Permission;
use App\Models\PancakeUserDailyCallReport;
use App\Models\PancakeUserPosDailyReport;
use App\Models\Shop;
use App\Models\Team;
use App\Models\Workspace;
use App\Support\TeamVisibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * The "viewing as team" switcher, applied to CSR analytics.
 *
 * Both rollups are keyed by shop and a shop belongs to teams, so the page
 * narrows down the same path Orders and Pages take — pick a team and every
 * figure on the page covers only that team's shops.
 */
beforeEach(function () {
    ['user' => $this->owner, 'workspace' => $this->workspace] = makeWorkspaceWithOwner();
});

/** A team with a shop of its own, and a CSR whose day landed in that shop. */
function teamWithCsrDay(Workspace $workspace, string $csrName, float $sales, int $callSeconds): array
{
    $team = Team::factory()->create(['workspace_id' => $workspace->id]);
    $shop = Shop::factory()->create(['workspace_id' => $workspace->id]);
    $team->shops()->attach($shop->id);

    $csr = PancakeUser::create(['name' => $csrName]);
    // The breakdown table lists CSRs through this pivot, so a CSR with no shop
    // link is not on the page at all, team filter or no team filter. The pivot
    // carries a uuid primary key of its own, hence the explicit insert.
    DB::table('pancake_shop_users')->insert([
        'id' => (string) Str::uuid(),
        'shop_id' => $shop->id,
        'user_id' => $csr->id,
    ]);

    PancakeUserPosDailyReport::create([
        'workspace_id' => $workspace->id,
        'pancake_user_id' => $csr->id,
        'shop_id' => $shop->id,
        'date' => '2026-08-02',
        'total_orders' => 1,
        'total_sales' => $sales,
    ]);

    PancakeUserDailyCallReport::create([
        'workspace_id' => $workspace->id,
        'pancake_user_id' => $csr->id,
        'shop_id' => $shop->id,
        'date' => '2026-08-02',
        'total_called' => 1,
        'total_call_time' => $callSeconds,
        'total_rmo_called' => 1,
        'total_rmo_call_time' => $callSeconds,
        'total_rmo_real_called' => 1,
        'total_rmo_connected_called' => 1,
        'longest_rmo_call_time' => $callSeconds,
        'total_rmo_assigned_count' => 1,
        'total_rmo_confirmed_count' => 1,
    ]);

    return ['team' => $team, 'shop' => $shop, 'csr' => $csr];
}

function teamStat($owner, Workspace $workspace, string $stat, ?int $teamId): TestResponse
{
    $query = http_build_query(array_filter([
        'from' => '2026-08-01',
        'to' => '2026-08-05',
        'team_id' => $teamId ?? 'all',
    ]));

    return test()->actingAs($owner)->getJson(
        "/api/workspaces/{$workspace->slug}/csrs/stats/{$stat}?{$query}"
    );
}

test('with no team picked the owner sees every shop', function () {
    teamWithCsrDay($this->workspace, 'Angeline Mercado', 6000, 120);
    teamWithCsrDay($this->workspace, 'Someone Else', 4000, 60);

    teamStat($this->owner, $this->workspace, 'analytics-rmo-time', null)
        ->assertOk()
        ->assertJsonPath('value', 180)
        ->assertJsonPath('calls', 2);
});

test('picking a team narrows the call figures to its shops', function () {
    ['team' => $team] = teamWithCsrDay($this->workspace, 'Angeline Mercado', 6000, 120);
    teamWithCsrDay($this->workspace, 'Someone Else', 4000, 60);

    teamStat($this->owner, $this->workspace, 'analytics-rmo-time', $team->id)
        ->assertOk()
        ->assertJsonPath('value', 120)
        ->assertJsonPath('calls', 1);
});

test('the daily effort chart narrows with the team too', function () {
    ['team' => $team] = teamWithCsrDay($this->workspace, 'Angeline Mercado', 6000, 120);
    teamWithCsrDay($this->workspace, 'Someone Else', 4000, 60);

    $days = collect(teamStat($this->owner, $this->workspace, 'analytics-daily-effort', $team->id)->json('days'));

    expect((int) $days->sum('calls'))->toBe(1)
        ->and((int) $days->firstWhere('date', '2026-08-02')['real'])->toBe(1);
});

test('the sales leader is drawn from the picked team only', function () {
    ['team' => $team] = teamWithCsrDay($this->workspace, 'Angeline Mercado', 6000, 120);
    teamWithCsrDay($this->workspace, 'Someone Else', 40000, 60);

    // The bigger seller is in the other team, so they cannot take the crown.
    teamStat($this->owner, $this->workspace, 'analytics-leader-sales', $team->id)
        ->assertJsonPath('leader.name', 'Angeline Mercado')
        ->assertJsonPath('leader.value', 6000);
});

test('the comparison panel lists only the picked team’s CSRs', function () {
    ['team' => $team] = teamWithCsrDay($this->workspace, 'Angeline Mercado', 6000, 120);
    teamWithCsrDay($this->workspace, 'Someone Else', 4000, 60);

    $sales = collect(teamStat($this->owner, $this->workspace, 'analytics-comparison', $team->id)->json('metrics'))
        ->firstWhere('key', 'sales');

    expect(collect($sales['rows'])->pluck('name')->all())->toBe(['Angeline Mercado']);
});

test('the RMO called leader narrows as well', function () {
    ['team' => $team] = teamWithCsrDay($this->workspace, 'Angeline Mercado', 6000, 120);
    teamWithCsrDay($this->workspace, 'Someone Else', 4000, 60);

    teamStat($this->owner, $this->workspace, 'analytics-leader-rmo-called', $team->id)
        ->assertJsonPath('leader.name', 'Angeline Mercado');
});

test('a member of one team sees only their own without picking anything', function () {
    ['team' => $team] = teamWithCsrDay($this->workspace, 'Angeline Mercado', 6000, 120);
    teamWithCsrDay($this->workspace, 'Someone Else', 4000, 60);

    // Holds the analytics permission but not "View All Workspace Data", so the
    // team they are in is the whole of what they can see.
    $member = makeMemberWithPermissions($this->workspace, [Permission::ViewCsrAnalytics->value]);
    $team->members()->attach($member->id);

    teamStat($member, $this->workspace, 'analytics-rmo-time', null)
        ->assertOk()
        ->assertJsonPath('value', 120);
});

test('a scoped viewer in no team sees nothing rather than everything', function () {
    teamWithCsrDay($this->workspace, 'Angeline Mercado', 6000, 120);

    $member = makeMemberWithPermissions($this->workspace, [Permission::ViewCsrAnalytics->value]);

    // Fail-closed: no team, so no shops, so no figures.
    expect(TeamVisibility::scopeShopIds($member->fresh(), $this->workspace))->toBe([]);

    teamStat($member, $this->workspace, 'analytics-rmo-time', null)
        ->assertOk()
        ->assertJsonPath('value', 0);
});

test('the breakdown table on the page narrows with the team', function () {
    ['team' => $team] = teamWithCsrDay($this->workspace, 'Angeline Mercado', 6000, 120);
    teamWithCsrDay($this->workspace, 'Someone Else', 4000, 60);

    $response = $this->actingAs($this->owner)->get(
        "/workspaces/{$this->workspace->slug}/csr/analytics?from=2026-08-01&to=2026-08-05&team_id={$team->id}"
    );

    $response->assertOk();

    $rows = collect($response->viewData('page')['props']['records']['data'])
        ->filter(fn ($row) => (float) $row['total_sales'] > 0)
        ->pluck('name')
        ->values()
        ->all();

    expect($rows)->toBe(['Angeline Mercado']);
});
