<?php

use App\Models\PancakeUserDailyCallReport;
use App\Models\PancakeUserPosDailyReport;
use App\Models\Shop;
use App\Models\Team;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * The CSR breakdown table's columns.
 *
 * The table carries every metric the two rollups hold, so the page can offer
 * them all in the Columns menu. These pin that: a column added to either
 * rollup and not surfaced here fails the drift guard below.
 */
beforeEach(function () {
    ['user' => $this->owner, 'workspace' => $this->workspace] = makeWorkspaceWithOwner();
});

/** The bookkeeping columns on both rollups — not metrics, so not table columns. */
const BREAKDOWN_NON_METRIC_COLUMNS = [
    'id', 'workspace_id', 'pancake_user_id', 'shop_id', 'date', 'created_at', 'updated_at',
];

/** A CSR with one day in each rollup, listed through the shop pivot the page reads. */
function breakdownCsr(Workspace $workspace, string $name, array $pos = [], array $calls = [], ?Team $team = null): PancakeUser
{
    $shop = Shop::factory()->create(['workspace_id' => $workspace->id]);

    // Both rollups are keyed by shop, and a shop belongs to teams — the path
    // the "viewing as team" switcher narrows the page down.
    $team?->shops()->attach($shop->id);
    $csr = PancakeUser::create(['name' => $name]);

    // The breakdown lists CSRs through this pivot, so one with no shop link is
    // not on the page at all. The pivot carries a uuid key of its own.
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
        ...$pos,
    ]);

    PancakeUserDailyCallReport::create([
        'workspace_id' => $workspace->id,
        'pancake_user_id' => $csr->id,
        'shop_id' => $shop->id,
        'date' => '2026-08-02',
        ...$calls,
    ]);

    return $csr;
}

/** Every breakdown row, in the order the page received them. */
function breakdownRows($owner, Workspace $workspace, string $query = ''): array
{
    $response = test()->actingAs($owner)->get(
        "/workspaces/{$workspace->slug}/csr/analytics?from=2026-08-01&to=2026-08-05&{$query}"
    );

    $response->assertOk();

    return $response->viewData('page')['props']['records']['data'];
}

/** The breakdown row for $name, as the page receives it. */
function breakdownRow($owner, Workspace $workspace, string $name): array
{
    $response = test()->actingAs($owner)->get(
        "/workspaces/{$workspace->slug}/csr/analytics?from=2026-08-01&to=2026-08-05"
    );

    $response->assertOk();

    return collect($response->viewData('page')['props']['records']['data'])
        ->firstWhere('name', $name);
}

test('the row carries every metric column both rollups hold', function () {
    breakdownCsr($this->workspace, 'Mariel Bautista');

    $row = breakdownRow($this->owner, $this->workspace, 'Mariel Bautista');

    // Derived from the schema rather than listed by hand: add a column to
    // either rollup and this says the breakdown has not caught up.
    $posMetrics = collect(Schema::getColumnListing('pancake_user_pos_daily_reports'))
        ->diff(BREAKDOWN_NON_METRIC_COLUMNS)
        // The two amounts travel under the `total_` names the table has always
        // used for them; every other column keeps its own name.
        ->map(fn ($column) => match ($column) {
            'delivered' => 'total_delivered',
            'returning' => 'total_returning',
            default => $column,
        });

    $callMetrics = collect(Schema::getColumnListing('pancake_user_daily_call_reports'))
        ->diff(BREAKDOWN_NON_METRIC_COLUMNS);

    expect(array_keys($row))
        ->toContain(...$posMetrics->all())
        ->toContain(...$callMetrics->all())
        // The two the server derives, so they sort like any other column.
        ->toContain('rts_rate', 'rmo_percentage');
});

test('every call figure is the report\'s own column, not a rename of another', function () {
    // Deliberately all different: an alias crossing two of these over would
    // otherwise pass on equal numbers.
    breakdownCsr($this->workspace, 'Mariel Bautista', calls: [
        'total_called' => 11,
        'total_call_time' => 12,
        'total_rmo_called' => 13,
        'total_rmo_call_time' => 14,
        'total_rmo_connected_called' => 15,
        'total_rmo_real_called' => 16,
        'longest_rmo_call_time' => 17,
        'total_rmo_customer_called' => 18,
        'total_rmo_customer_call_time' => 19,
        'total_rmo_rider_called' => 20,
        'total_rmo_rider_call_time' => 21,
        'total_rmo_assigned_count' => 22,
        'total_rmo_confirmed_count' => 23,
        'total_verification_called' => 24,
        'total_verification_call_time' => 25,
    ]);

    $row = breakdownRow($this->owner, $this->workspace, 'Mariel Bautista');

    expect((int) $row['total_called'])->toBe(11)
        ->and((int) $row['total_call_time'])->toBe(12)
        ->and((int) $row['total_rmo_called'])->toBe(13)
        ->and((int) $row['total_rmo_call_time'])->toBe(14)
        ->and((int) $row['total_rmo_connected_called'])->toBe(15)
        ->and((int) $row['total_rmo_real_called'])->toBe(16)
        ->and((int) $row['longest_rmo_call_time'])->toBe(17)
        ->and((int) $row['total_rmo_customer_called'])->toBe(18)
        ->and((int) $row['total_rmo_customer_call_time'])->toBe(19)
        ->and((int) $row['total_rmo_rider_called'])->toBe(20)
        ->and((int) $row['total_rmo_rider_call_time'])->toBe(21)
        ->and((int) $row['total_rmo_assigned_count'])->toBe(22)
        ->and((int) $row['total_rmo_confirmed_count'])->toBe(23)
        ->and((int) $row['total_verification_called'])->toBe(24)
        ->and((int) $row['total_verification_call_time'])->toBe(25);
});

test('the parcel counts come through beside the money they belong to', function () {
    breakdownCsr($this->workspace, 'Mariel Bautista', pos: [
        'delivered' => 900,
        'delivered_count' => 3,
        'returning' => 100,
        'returning_count' => 1,
        // What SyncCsrDailyRecord stores for those amounts: 100 of 1000.
        'rts_rate' => 10,
    ]);

    $row = breakdownRow($this->owner, $this->workspace, 'Mariel Bautista');

    expect((float) $row['total_delivered'])->toBe(900.0)
        ->and((int) $row['delivered_count'])->toBe(3)
        ->and((float) $row['total_returning'])->toBe(100.0)
        ->and((int) $row['returning_count'])->toBe(1)
        ->and((float) $row['rts_rate'])->toBe(10.0);
});

test('a range sums the days in it, but the longest call is a max', function () {
    $csr = breakdownCsr($this->workspace, 'Mariel Bautista', calls: [
        'total_rmo_called' => 2,
        'longest_rmo_call_time' => 300,
    ]);

    // A second day, with a shorter longest call than the first.
    PancakeUserDailyCallReport::create([
        'workspace_id' => $this->workspace->id,
        'pancake_user_id' => $csr->id,
        'shop_id' => 0,
        'date' => '2026-08-03',
        'total_rmo_called' => 3,
        'longest_rmo_call_time' => 120,
    ]);

    $row = breakdownRow($this->owner, $this->workspace, 'Mariel Bautista');

    // Counts add up over the range; the longest call is the longest, not the
    // sum of each day's longest.
    expect((int) $row['total_rmo_called'])->toBe(5)
        ->and((int) $row['longest_rmo_call_time'])->toBe(300);
});

test('the ERP report answers zero for the parcel counts it has no columns for', function () {
    breakdownCsr($this->workspace, 'Mariel Bautista');

    // The ERP rollup carries neither count, so the query substitutes zero
    // rather than failing to compile — the row keeps one shape either way.
    $response = $this->actingAs($this->owner)->get(
        "/workspaces/{$this->workspace->slug}/csr/analytics?type=erp&from=2026-08-01&to=2026-08-05"
    );

    $response->assertOk();

    $row = collect($response->viewData('page')['props']['records']['data'])
        ->firstWhere('name', 'Mariel Bautista');

    expect((int) $row['delivered_count'])->toBe(0)
        ->and((int) $row['returning_count'])->toBe(0);
});

test('sorting on a newly offered column really orders the rows', function () {
    breakdownCsr($this->workspace, 'Fewer Verifications', calls: ['total_verification_called' => 2]);
    breakdownCsr($this->workspace, 'More Verifications', calls: ['total_verification_called' => 9]);

    // Not just a 200: a column in the menu that the server accepts but ignores
    // would look like a dead header to whoever switched it on.
    $descending = collect(breakdownRows($this->owner, $this->workspace, 'sort=-total_verification_called'))
        ->pluck('name')->all();

    $ascending = collect(breakdownRows($this->owner, $this->workspace, 'sort=total_verification_called'))
        ->pluck('name')->all();

    expect($descending)->toBe(['More Verifications', 'Fewer Verifications'])
        ->and($ascending)->toBe(['Fewer Verifications', 'More Verifications']);
});

test('RMO % is assigned over confirmed, and nothing over nothing is no rate', function () {
    breakdownCsr($this->workspace, 'Mariel Bautista', calls: [
        'total_rmo_assigned_count' => 30,
        'total_rmo_confirmed_count' => 40,
    ]);

    // Nothing confirmed: dividing by it is undefined, so the server sends 0 and
    // the column draws a dash rather than a clean 0%.
    breakdownCsr($this->workspace, 'Nothing Confirmed', calls: [
        'total_rmo_assigned_count' => 5,
        'total_rmo_confirmed_count' => 0,
    ]);

    expect((float) breakdownRow($this->owner, $this->workspace, 'Mariel Bautista')['rmo_percentage'])
        ->toBe(75.0)
        ->and((float) breakdownRow($this->owner, $this->workspace, 'Nothing Confirmed')['rmo_percentage'])
        ->toBe(0.0);
});

test('a CSR working two shops in a day is one row, not two', function () {
    $csr = breakdownCsr($this->workspace, 'Mariel Bautista', calls: [
        'total_called' => 4,
        'total_verification_call_time' => 60,
    ]);

    // The rollups split a CSR's day per shop. The breakdown is per CSR, so the
    // shops have to be summed back or the same person appears twice.
    PancakeUserDailyCallReport::create([
        'workspace_id' => $this->workspace->id,
        'pancake_user_id' => $csr->id,
        'shop_id' => Shop::factory()->create(['workspace_id' => $this->workspace->id])->id,
        'date' => '2026-08-02',
        'total_called' => 6,
        'total_verification_call_time' => 90,
    ]);

    $rows = collect(breakdownRows($this->owner, $this->workspace))
        ->where('name', 'Mariel Bautista');

    expect($rows)->toHaveCount(1)
        ->and((int) $rows->first()['total_called'])->toBe(10)
        ->and((int) $rows->first()['total_verification_call_time'])->toBe(150);
});

test('a CSR with no rollup day reads zero across every column', function () {
    $shop = Shop::factory()->create(['workspace_id' => $this->workspace->id]);
    $csr = PancakeUser::create(['name' => 'Idle CSR']);

    DB::table('pancake_shop_users')->insert([
        'id' => (string) Str::uuid(),
        'shop_id' => $shop->id,
        'user_id' => $csr->id,
    ]);

    $row = breakdownRow($this->owner, $this->workspace, 'Idle CSR');

    // They are on the page — the listing is the shop pivot, not the rollups —
    // so every figure has to be a zero rather than a null the column would
    // print as "NaN".
    $metrics = collect(Schema::getColumnListing('pancake_user_daily_call_reports'))
        ->diff(BREAKDOWN_NON_METRIC_COLUMNS)
        ->merge(['total_orders', 'total_sales', 'total_delivered', 'total_returning'])
        ->merge(['delivered_count', 'returning_count', 'rts_rate', 'rmo_percentage']);

    foreach ($metrics as $metric) {
        expect($row[$metric])->not->toBeNull()
            ->and((float) $row[$metric])->toBe(0.0);
    }
});

test('another workspace\'s rollup days are not in the row', function () {
    ['workspace' => $other] = makeWorkspaceWithOwner();

    $csr = breakdownCsr($this->workspace, 'Mariel Bautista', calls: ['total_called' => 3]);

    // The same CSR, a day of work, someone else's workspace. The rollups join
    // on pancake_user_id alone, so the workspace filter is the only thing
    // keeping the two apart.
    PancakeUserDailyCallReport::create([
        'workspace_id' => $other->id,
        'pancake_user_id' => $csr->id,
        'shop_id' => 0,
        'date' => '2026-08-02',
        'total_called' => 999,
    ]);

    expect((int) breakdownRow($this->owner, $this->workspace, 'Mariel Bautista')['total_called'])
        ->toBe(3);
});

test('the team filter narrows the newly offered columns too', function () {
    $team = Team::factory()->create(['workspace_id' => $this->workspace->id]);

    breakdownCsr($this->workspace, 'In The Team', calls: ['total_verification_called' => 7], team: $team);
    breakdownCsr($this->workspace, 'Another Team', calls: ['total_verification_called' => 5]);

    $rows = collect(breakdownRows($this->owner, $this->workspace, "team_id={$team->id}"))
        ->filter(fn ($row) => (int) $row['total_verification_called'] > 0)
        ->pluck('name')
        ->values()
        ->all();

    // The switcher narrows every figure on the page, so a column that was
    // hidden when the filter was written must narrow with the rest.
    expect($rows)->toBe(['In The Team']);
});

test('the RTS Rate card and the table\'s RTS Rate column both read the stored column', function () {
    // One CSR, so the workspace's rate and that CSR's row are the same figure.
    // The stored rate is deliberately not what the amounts imply — 2000 of
    // 10000 would be 20% — so a reader that divides the money instead of
    // reading the column shows 20 here and fails.
    breakdownCsr($this->workspace, 'Mariel Bautista', pos: [
        'returning' => 2000,
        'delivered' => 8000,
        'rts_rate' => 37.5,
    ]);

    $card = $this->actingAs($this->owner)
        ->getJson("/api/workspaces/{$this->workspace->slug}/csrs/stats/analytics-rts?from=2026-08-01&to=2026-08-05")
        ->assertOk()
        ->json();

    $column = (float) breakdownRow($this->owner, $this->workspace, 'Mariel Bautista')['rts_rate'];

    expect((float) $card['value'])->toBe(37.5)
        ->and($column)->toBe(37.5)
        // The amounts still travel beside it — the card's footnote is money.
        ->and((float) $card['returning_amount'])->toBe(2000.0);
});

test('an unwritten rts_rate reads zero on both, whatever the amounts say', function () {
    // The state of every row today: money settled, stored rate never written.
    // Both readers report 0.00 rather than the 20% the amounts imply, and
    // `sync:csr-daily-records --days=N` is what changes that.
    breakdownCsr($this->workspace, 'Mariel Bautista', pos: [
        'returning' => 2000,
        'delivered' => 8000,
    ]);

    $card = $this->actingAs($this->owner)
        ->getJson("/api/workspaces/{$this->workspace->slug}/csrs/stats/analytics-rts?from=2026-08-01&to=2026-08-05")
        ->assertOk()
        ->json();

    expect((float) $card['value'])->toBe(0.0)
        ->and((float) breakdownRow($this->owner, $this->workspace, 'Mariel Bautista')['rts_rate'])
        ->toBe(0.0);
});

test('every column the table offers can be sorted on', function () {
    breakdownCsr($this->workspace, 'Mariel Bautista');

    $columns = collect(Schema::getColumnListing('pancake_user_daily_call_reports'))
        ->diff(BREAKDOWN_NON_METRIC_COLUMNS)
        ->merge(['total_orders', 'total_sales', 'total_delivered', 'total_returning'])
        ->merge(['delivered_count', 'returning_count', 'rts_rate', 'rmo_percentage']);

    // A column offered in the Columns menu but missing from allowedSorts throws
    // on the click that sorts it, so the menu and the sort list move together.
    foreach ($columns as $column) {
        $this->actingAs($this->owner)
            ->get("/workspaces/{$this->workspace->slug}/csr/analytics?sort=-{$column}")
            ->assertOk();
    }
});
