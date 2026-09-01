<?php

use App\Models\Order;
use App\Models\PancakeUserRmoDailyReport;
use App\Models\User;
use App\Models\Workspace;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * The CSR comparison panel under the leaders.
 *
 * The leader cards name one winner per metric; this puts the field behind them
 * on one axis. It follows those cards' definitions exactly — sales are every
 * order a CSR confirmed, cancellations included; RTS is money back over money
 * settled; the two RMO metrics come off the nightly rollup — so a bar here and
 * the card above it can never disagree.
 *
 * All four metrics arrive in one response: they come from two scans, each of
 * which already carries what both of its metrics need, so a request per tab
 * would run the same two queries twice over.
 */
const CMP_FROM = '2026-08-15';
const CMP_TO = '2026-08-21';
const CMP_PREV_FROM = '2026-08-08';
const CMP_PREV_TO = '2026-08-14';

function comparison($owner, Workspace $workspace, ?string $from = null, ?string $to = null)
{
    $from ??= CMP_FROM;
    $to ??= CMP_TO;

    return test()->actingAs($owner)->getJson(
        "/api/workspaces/{$workspace->slug}/csrs/stats/analytics-comparison?from={$from}&to={$to}"
    );
}

/** The block for one metric key, out of the ordered list the endpoint returns. */
function metricBlock($response, string $key): array
{
    return collect($response->json('metrics'))->firstWhere('key', $key);
}

function cmpCsr(string $name): PancakeUser
{
    return PancakeUser::create(['name' => $name]);
}

/** An order this CSR confirmed, at this amount, on this day. */
function cmpSale(Workspace $workspace, PancakeUser $csr, string $confirmedAt, float $amount): Order
{
    return Order::factory()->forWorkspace($workspace)->create([
        'status' => 1,
        'confirmed_by' => $csr->id,
        'confirmed_at' => $confirmedAt,
        'final_amount' => $amount,
    ]);
}

/** A parcel of theirs that settled — returned (status 4) or delivered (3). */
function cmpSettled(Workspace $workspace, PancakeUser $csr, string $on, float $amount, bool $returned): Order
{
    return Order::factory()->forWorkspace($workspace)->create([
        'status' => $returned ? 4 : 3,
        'confirmed_by' => $csr->id,
        'confirmed_at' => null,
        'final_amount' => $amount,
        'returning_at' => $returned ? $on : null,
        'delivered_at' => $returned ? null : $on,
    ]);
}

function cmpRollup(Workspace $workspace, PancakeUser $csr, string $date, array $figures): void
{
    PancakeUserRmoDailyReport::create([
        'workspace_id' => $workspace->id,
        'pancake_user_id' => $csr->id,
        'date' => $date,
        'total_called' => $figures['called'] ?? 0,
        'total_confirmed' => $figures['confirmed'] ?? 0,
        'total_call_time' => $figures['seconds'] ?? 0,
        'total_rmo_call_attempts' => $figures['attempts'] ?? 0,
    ]);
}

beforeEach(function () {
    ['user' => $this->owner, 'workspace' => $this->workspace] = makeWorkspaceWithOwner();
});

test('sales rank the CSRs and carry the period average', function () {
    $top = cmpCsr('Angeline Mercado');
    $mid = cmpCsr('Jhon Paul Reyes');

    cmpSale($this->workspace, $top, '2026-08-16 09:00:00', 6000);
    cmpSale($this->workspace, $mid, '2026-08-17 09:00:00', 2000);
    // Outside the window on purpose.
    cmpSale($this->workspace, $mid, '2026-08-30 09:00:00', 9999);

    $sales = metricBlock(comparison($this->owner, $this->workspace)->assertOk(), 'sales');

    expect(collect($sales['rows'])->pluck('name')->all())
        ->toBe(['Angeline Mercado', 'Jhon Paul Reyes']);
    expect($sales['rows'][0]['value'])->toEqual(6000);
    expect($sales['average'])->toEqual(4000);
    expect($sales['higher_is_better'])->toBeTrue();
});

test('a CSR is measured against their own previous period', function () {
    $csr = cmpCsr('Angeline Mercado');

    cmpSale($this->workspace, $csr, '2026-08-16 09:00:00', 1200);
    // The equally long stretch ending the day before the range.
    cmpSale($this->workspace, $csr, '2026-08-10 09:00:00', 1000);

    $row = metricBlock(comparison($this->owner, $this->workspace), 'sales')['rows'][0];

    expect($row['previous_value'])->toEqual(1000);
    expect($row['change'])->toEqual(20);
});

test('a CSR with no previous figure has no change rather than zero', function () {
    $csr = cmpCsr('New Starter');
    cmpSale($this->workspace, $csr, '2026-08-16 09:00:00', 500);

    $row = metricBlock(comparison($this->owner, $this->workspace), 'sales')['rows'][0];

    expect($row['previous_value'])->toBeNull();
    expect($row['change'])->toBeNull();
});

test('the previous period is the equally long stretch before the range', function () {
    comparison($this->owner, $this->workspace)
        ->assertJsonPath('previous_period.from', CMP_PREV_FROM)
        ->assertJsonPath('previous_period.to', CMP_PREV_TO);
});

test('RTS ranks lowest first and moves in percentage points', function () {
    $clean = cmpCsr('Precious Ann Dela Cruz');
    $worse = cmpCsr('Rafael Gutierrez');

    // 10% back this period, 20% back last — an improvement of 10 points.
    cmpSettled($this->workspace, $clean, '2026-08-16 09:00:00', 100, true);
    cmpSettled($this->workspace, $clean, '2026-08-16 09:00:00', 900, false);
    cmpSettled($this->workspace, $clean, '2026-08-10 09:00:00', 200, true);
    cmpSettled($this->workspace, $clean, '2026-08-10 09:00:00', 800, false);

    cmpSettled($this->workspace, $worse, '2026-08-16 09:00:00', 500, true);
    cmpSettled($this->workspace, $worse, '2026-08-16 09:00:00', 500, false);

    $rts = metricBlock(comparison($this->owner, $this->workspace), 'rts');

    expect($rts['higher_is_better'])->toBeFalse();
    expect($rts['delta_unit'])->toBe(' pts');
    expect(collect($rts['rows'])->pluck('name')->all())
        ->toBe(['Precious Ann Dela Cruz', 'Rafael Gutierrez']);
    expect($rts['rows'][0]['value'])->toEqual(10);
    expect($rts['rows'][0]['change'])->toEqual(-10);
});

test('the RMO metrics come off the nightly rollup', function () {
    $csr = cmpCsr('Mariel Bautista');

    cmpRollup($this->workspace, $csr, '2026-08-16', ['called' => 8, 'confirmed' => 10, 'seconds' => 600]);
    cmpRollup($this->workspace, $csr, '2026-08-09', ['called' => 5, 'confirmed' => 10, 'seconds' => 300]);

    $response = comparison($this->owner, $this->workspace);

    $called = metricBlock($response, 'rmo_called');
    expect($called['rows'][0]['value'])->toEqual(80);
    // 80% against 50% is thirty points, not a 60% climb.
    expect($called['rows'][0]['change'])->toEqual(30);

    $time = metricBlock($response, 'call_time');
    expect($time['rows'][0]['value'])->toEqual(600);
    expect($time['rows'][0]['change'])->toEqual(100);
});

test('a CSR who did nothing this period is left out of that metric', function () {
    $active = cmpCsr('Angeline Mercado');
    $quiet = cmpCsr('Dennis Ocampo');

    cmpSale($this->workspace, $active, '2026-08-16 09:00:00', 1000);
    cmpSale($this->workspace, $quiet, '2026-08-10 09:00:00', 5000);

    $rows = metricBlock(comparison($this->owner, $this->workspace), 'sales')['rows'];

    expect(collect($rows)->pluck('name')->all())->toBe(['Angeline Mercado']);
});

test('the panel lists at most eight, and says how many qualified', function () {
    foreach (range(1, 10) as $i) {
        cmpSale($this->workspace, cmpCsr("CSR {$i}"), '2026-08-16 09:00:00', $i * 100);
    }

    $sales = metricBlock(comparison($this->owner, $this->workspace), 'sales');

    expect($sales['rows'])->toHaveCount(8);
    expect($sales['total'])->toBe(10);
    // The average is over all ten, not the eight shown — otherwise half the
    // listed field is above average by construction.
    expect($sales['average'])->toEqual(550);
});

test('a CSR keeps their colour when the metric changes', function () {
    $second = cmpCsr('Jhon Paul Reyes');
    $first = cmpCsr('Angeline Mercado');

    cmpSale($this->workspace, $first, '2026-08-16 09:00:00', 9000);
    cmpSale($this->workspace, $second, '2026-08-16 09:00:00', 1000);

    // Reversed on call time: the smaller seller spent the most time on calls.
    cmpRollup($this->workspace, $second, '2026-08-16', ['seconds' => 900, 'called' => 1, 'confirmed' => 1]);
    cmpRollup($this->workspace, $first, '2026-08-16', ['seconds' => 100, 'called' => 1, 'confirmed' => 1]);

    $response = comparison($this->owner, $this->workspace);

    $slots = collect(metricBlock($response, 'sales')['rows'])
        ->pluck('color_slot', 'name');
    $timeSlots = collect(metricBlock($response, 'call_time')['rows'])
        ->pluck('color_slot', 'name');

    // Sales ranks them first/second, call time the other way round — the hues
    // follow the person, so both tabs paint each of them the same.
    expect($timeSlots['Angeline Mercado'])->toBe($slots['Angeline Mercado']);
    expect($timeSlots['Jhon Paul Reyes'])->toBe($slots['Jhon Paul Reyes']);
    expect($slots['Angeline Mercado'])->not->toBe($slots['Jhon Paul Reyes']);
});

test('another workspace\'s CSRs are not in the comparison', function () {
    $other = Workspace::factory()->forOwner(User::factory()->create())->create();
    $csr = cmpCsr('Somebody Else');
    cmpSale($other, $csr, '2026-08-16 09:00:00', 5000);

    $sales = metricBlock(comparison($this->owner, $this->workspace), 'sales');

    expect($sales['rows'])->toBeEmpty();
    expect($sales['average'])->toBeNull();
});

test('the endpoint needs the CSR analytics permission', function () {
    comparison(makeWorkspaceMember($this->workspace), $this->workspace)
        ->assertForbidden();
});

/**
 * The tab is a URL parameter, not browser state — a reload, a bookmark or a
 * link passed to a colleague opens on the metric that was being read, and the
 * page ships the key so the first paint is already on the right tab.
 */
test('the analytics page opens on the comparison tab named in the URL', function () {
    test()->actingAs($this->owner)
        ->get("/workspaces/{$this->workspace->slug}/csr/analytics?comparison=call_time")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('query.comparison', 'call_time'));
});

test('the comparison tab defaults to sales, and an unknown key falls back to it', function () {
    $page = fn (string $url) => test()->actingAs($this->owner)->get($url)->assertOk();
    $base = "/workspaces/{$this->workspace->slug}/csr/analytics";

    $page($base)->assertInertia(fn ($p) => $p->where('query.comparison', 'sales'));
    $page("{$base}?comparison=nonsense")
        ->assertInertia(fn ($p) => $p->where('query.comparison', 'sales'));
});
