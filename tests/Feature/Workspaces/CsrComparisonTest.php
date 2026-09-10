<?php

use App\Jobs\SyncCsrDailyRecord;
use App\Models\Order;
use App\Models\PancakeUserDailyCallReport;
use App\Models\User;
use App\Models\Workspace;
use App\Support\CsrComparisonMetrics;
use Carbon\CarbonImmutable;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * The CSR comparison panel under the leaders.
 *
 * The leader cards name one winner per metric; this puts the field behind them
 * on one axis. It follows those cards' definitions exactly — sales are every
 * order a CSR confirmed, cancellations included; RTS is money back over money
 * settled; the RMO metrics come off the nightly rollup — so a bar here and the
 * card above it can never disagree.
 *
 * The dropdown offers every figure the two rollups carry (see
 * CsrComparisonMetrics), and a request asks for the one being read: the panel
 * sends `?metric=`, and the scan covers that metric's columns off one rollup
 * rather than every column of both.
 */
const CMP_FROM = '2026-08-15';
const CMP_TO = '2026-08-21';
const CMP_PREV_FROM = '2026-08-08';
const CMP_PREV_TO = '2026-08-14';

/**
 * Write the nightly POS rollup across both periods, as the scheduler does. The
 * previous period is the same length again, immediately before, so start there.
 */
function cmpSyncPos(string $from, string $to): void
{
    $start = CarbonImmutable::parse($from);
    $end = CarbonImmutable::parse($to);
    $cursor = $start->subDays($start->diffInDays($end) + 1);

    while ($cursor->lessThanOrEqualTo($end)) {
        (new SyncCsrDailyRecord($cursor->toDateString()))->handle();
        $cursor = $cursor->addDay();
    }
}

/** The endpoint, asked for one metric — the one the panel would be showing. */
function comparison($owner, Workspace $workspace, string $metric = 'total_sales', ?string $from = null, ?string $to = null)
{
    $from ??= CMP_FROM;
    $to ??= CMP_TO;

    cmpSyncPos($from, $to);

    return test()->actingAs($owner)->getJson(
        "/api/workspaces/{$workspace->slug}/csrs/stats/analytics-comparison?from={$from}&to={$to}&metric={$metric}"
    );
}

/** The block the endpoint answered with. */
function metricBlock($response): array
{
    return $response->json('metric');
}

/**
 * A CSR. The id can be pinned where the test cares about the chart colour,
 * which the endpoint takes from the id rather than from the ranking.
 */
function cmpCsr(string $name, ?string $id = null): PancakeUser
{
    return PancakeUser::create(array_filter(['name' => $name, 'id' => $id]));
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

/** One day of the call rollup for this CSR, with the columns named. */
function cmpCallRow(Workspace $workspace, PancakeUser $csr, string $date, array $columns): void
{
    // Every reader sums across shops, so which shop these land on is immaterial.
    PancakeUserDailyCallReport::create([
        'workspace_id' => $workspace->id,
        'pancake_user_id' => $csr->id,
        'shop_id' => 0,
        'date' => $date,
        ...$columns,
    ]);
}

function cmpRollup(Workspace $workspace, PancakeUser $csr, string $date, array $figures): void
{
    cmpCallRow($workspace, $csr, $date, [
        'total_rmo_assigned_count' => $figures['called'] ?? 0,
        'total_rmo_confirmed_count' => $figures['confirmed'] ?? 0,
        'total_call_time' => $figures['seconds'] ?? 0,
        'total_rmo_call_time' => $figures['seconds'] ?? 0,
        'total_called' => $figures['attempts'] ?? 0,
        'total_rmo_called' => $figures['attempts'] ?? 0,
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

    $sales = metricBlock(comparison($this->owner, $this->workspace)->assertOk());

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

    $row = metricBlock(comparison($this->owner, $this->workspace))['rows'][0];

    expect($row['previous_value'])->toEqual(1000);
    expect($row['change'])->toEqual(20);
});

test('a CSR with no previous figure has no change rather than zero', function () {
    $csr = cmpCsr('New Starter');
    cmpSale($this->workspace, $csr, '2026-08-16 09:00:00', 500);

    $row = metricBlock(comparison($this->owner, $this->workspace))['rows'][0];

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

    $rts = metricBlock(comparison($this->owner, $this->workspace, 'rts_rate'));

    expect($rts['higher_is_better'])->toBeFalse();
    expect($rts['delta_unit'])->toBe(' pts');
    expect(collect($rts['rows'])->pluck('name')->all())
        ->toBe(['Precious Ann Dela Cruz', 'Rafael Gutierrez']);
    expect($rts['rows'][0]['value'])->toEqual(10);
    expect($rts['rows'][0]['change'])->toEqual(-10);
});

test('a CSR whose parcels settled for nothing has no RTS to plot', function () {
    $real = cmpCsr('Precious Ann Dela Cruz');
    $zeroValue = cmpCsr('Rafael Gutierrez');

    cmpSettled($this->workspace, $real, '2026-08-16 09:00:00', 100, true);
    cmpSettled($this->workspace, $real, '2026-08-16 09:00:00', 900, false);

    // Eligibility is money settled, as on the card and the leader — 0/0 is no
    // rate, so this CSR is left out rather than plotted at 0%.
    cmpSettled($this->workspace, $zeroValue, '2026-08-16 09:00:00', 0, false);

    $rts = metricBlock(comparison($this->owner, $this->workspace, 'rts_rate'));

    expect(collect($rts['rows'])->pluck('name')->all())->toBe(['Precious Ann Dela Cruz']);
    expect($rts['total'])->toBe(1);
});

test('the RMO metrics come off the nightly rollup', function () {
    $csr = cmpCsr('Mariel Bautista');

    cmpRollup($this->workspace, $csr, '2026-08-16', ['called' => 8, 'confirmed' => 10, 'seconds' => 600]);
    cmpRollup($this->workspace, $csr, '2026-08-09', ['called' => 5, 'confirmed' => 10, 'seconds' => 300]);

    $called = metricBlock(comparison($this->owner, $this->workspace, 'rmo_called_rate'));
    expect($called['rows'][0]['value'])->toEqual(80);
    // 80% against 50% is thirty points, not a 60% climb.
    expect($called['rows'][0]['change'])->toEqual(30);

    $time = metricBlock(comparison($this->owner, $this->workspace, 'total_rmo_call_time'));
    expect($time['rows'][0]['value'])->toEqual(600);
    expect($time['rows'][0]['change'])->toEqual(100);
});

test('a CSR who called none of their RMO deliveries is left out of the rate', function () {
    $working = cmpCsr('Mariel Bautista');
    $idle = cmpCsr('Cylde Dela Cruz');

    cmpRollup($this->workspace, $working, '2026-08-16', ['called' => 8, 'confirmed' => 10]);
    // Most of the roster looks like this: deliveries confirmed, none of them
    // called. A rate of 0% is nothing done, not a figure to rank — it used to
    // sit at the bottom of the chart and pull the average line down with it.
    cmpRollup($this->workspace, $idle, '2026-08-16', ['called' => 0, 'confirmed' => 66]);

    $called = metricBlock(comparison($this->owner, $this->workspace, 'rmo_called_rate'));

    expect(collect($called['rows'])->pluck('name')->all())->toBe(['Mariel Bautista']);
    expect($called['total'])->toBe(1);
    // 80 on its own, not the 40 that averaging in a zero would have given.
    expect($called['average'])->toEqual(80);
});

test('a rate of zero the period before still reads as a climb', function () {
    $csr = cmpCsr('Mariel Bautista');

    cmpRollup($this->workspace, $csr, '2026-08-16', ['called' => 6, 'confirmed' => 10]);
    // Nothing called last period, plenty confirmed. Only the ranked period
    // drops its zeros, so this stays a comparison rather than becoming a dash.
    cmpRollup($this->workspace, $csr, '2026-08-09', ['called' => 0, 'confirmed' => 10]);

    $row = metricBlock(comparison($this->owner, $this->workspace, 'rmo_called_rate'))['rows'][0];

    expect($row['value'])->toEqual(60);
    expect($row['previous_value'])->toEqual(0);
    expect($row['change'])->toEqual(60);
});

test('a CSR who returned nothing is left out of RTS rate', function () {
    $some = cmpCsr('Rafael Gutierrez');
    $none = cmpCsr('Precious Ann Dela Cruz');

    cmpSettled($this->workspace, $some, '2026-08-16 09:00:00', 100, true);
    cmpSettled($this->workspace, $some, '2026-08-16 09:00:00', 900, false);
    // A clean 0% — the best RTS there is. It goes the same way as every other
    // zero: dropped from the ranked period, so the chart plots the CSRs with
    // parcels coming back rather than the ones with none.
    cmpSettled($this->workspace, $none, '2026-08-16 09:00:00', 1000, false);

    $rts = metricBlock(comparison($this->owner, $this->workspace, 'rts_rate'));

    expect(collect($rts['rows'])->pluck('name')->all())->toBe(['Rafael Gutierrez']);
    expect($rts['total'])->toBe(1);
});

test('a CSR who did nothing this period is left out of that metric', function () {
    $active = cmpCsr('Angeline Mercado');
    $quiet = cmpCsr('Dennis Ocampo');

    cmpSale($this->workspace, $active, '2026-08-16 09:00:00', 1000);
    cmpSale($this->workspace, $quiet, '2026-08-10 09:00:00', 5000);

    $rows = metricBlock(comparison($this->owner, $this->workspace))['rows'];

    expect(collect($rows)->pluck('name')->all())->toBe(['Angeline Mercado']);
});

test('every CSR with a figure is listed, however long the roster', function () {
    foreach (range(1, 120) as $i) {
        cmpSale($this->workspace, cmpCsr("CSR {$i}"), '2026-08-16 09:00:00', $i * 100);
    }

    $sales = metricBlock(comparison($this->owner, $this->workspace));

    // The whole field, not a top few — a CSR with figures is never missing from
    // the chart their figures belong on.
    expect($sales['rows'])->toHaveCount(120);
    expect($sales['total'])->toBe(120);
    expect($sales['average'])->toEqual(6050);
    expect($sales['rows'][0]['value'])->toEqual(12000);
});

/**
 * The colour comes from the CSR's id, which is what lets it survive a metric
 * change now that each metric is its own request — the response that draws the
 * next one never sees the last one's ordering.
 */
test('a CSR keeps their colour when the metric changes', function () {
    $first = cmpCsr('Angeline Mercado', '00000000-0000-4000-8000-000000000000');
    $second = cmpCsr('Jhon Paul Reyes', '00000000-0000-4000-8000-000000000001');

    cmpSale($this->workspace, $first, '2026-08-16 09:00:00', 9000);
    cmpSale($this->workspace, $second, '2026-08-16 09:00:00', 1000);

    // Reversed on call time: the smaller seller spent the most time on calls.
    cmpRollup($this->workspace, $second, '2026-08-16', ['seconds' => 900, 'called' => 1, 'confirmed' => 1]);
    cmpRollup($this->workspace, $first, '2026-08-16', ['seconds' => 100, 'called' => 1, 'confirmed' => 1]);

    $slots = collect(metricBlock(comparison($this->owner, $this->workspace))['rows'])
        ->pluck('color_slot', 'name');
    $timeSlots = collect(metricBlock(comparison($this->owner, $this->workspace, 'total_rmo_call_time'))['rows'])
        ->pluck('color_slot', 'name');

    // Sales ranks them first/second, call time the other way round — the hues
    // follow the person, so both selections paint each of them the same.
    expect($timeSlots['Angeline Mercado'])->toBe($slots['Angeline Mercado']);
    expect($timeSlots['Jhon Paul Reyes'])->toBe($slots['Jhon Paul Reyes']);
    expect($slots['Angeline Mercado'])->not->toBe($slots['Jhon Paul Reyes']);
});

test('another workspace\'s CSRs are not in the comparison', function () {
    $other = Workspace::factory()->forOwner(User::factory()->create())->create();
    $csr = cmpCsr('Somebody Else');
    cmpSale($other, $csr, '2026-08-16 09:00:00', 5000);

    $sales = metricBlock(comparison($this->owner, $this->workspace));

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
test('the analytics page opens on the metric named in the URL', function () {
    test()->actingAs($this->owner)
        ->get("/workspaces/{$this->workspace->slug}/csr/analytics?comparison=total_verification_called")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('query.comparison', 'total_verification_called'));
});

test('a link made when the panel had tabs opens on what that tab now names', function () {
    $page = fn (string $key) => test()->actingAs($this->owner)
        ->get("/workspaces/{$this->workspace->slug}/csr/analytics?comparison={$key}")
        ->assertOk();

    $page('sales')->assertInertia(fn ($p) => $p->where('query.comparison', 'total_sales'));
    $page('rts')->assertInertia(fn ($p) => $p->where('query.comparison', 'rts_rate'));
    $page('rmo_called')->assertInertia(fn ($p) => $p->where('query.comparison', 'rmo_called_rate'));
    $page('call_time')->assertInertia(fn ($p) => $p->where('query.comparison', 'total_rmo_call_time'));
});

test('the metric defaults to total sales, and an unknown key falls back to it', function () {
    $page = fn (string $url) => test()->actingAs($this->owner)->get($url)->assertOk();
    $base = "/workspaces/{$this->workspace->slug}/csr/analytics";

    $page($base)->assertInertia(fn ($p) => $p->where('query.comparison', 'total_sales'));
    $page("{$base}?comparison=nonsense")
        ->assertInertia(fn ($p) => $p->where('query.comparison', 'total_sales'));
});

/**
 * The dropdown is populated from the page, not from the response, so it is
 * usable while the figures behind it are still loading.
 */
test('the page ships every metric the dropdown lists', function () {
    test()->actingAs($this->owner)
        ->get("/workspaces/{$this->workspace->slug}/csr/analytics")
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->has('comparisonMetrics', count(CsrComparisonMetrics::keys()))
            ->where('comparisonMetrics.0.key', 'total_sales')
            ->where('comparisonMetrics.0.group', 'Orders')
            ->etc()
        );
});

/*
 |------------------------------------------------------------------------------
 | Every column, not just the four the panel used to tab between
 |------------------------------------------------------------------------------
 |
 | One request per metric, so what each of them reads is worth stating.
 */

test('the endpoint answers with the metric it was asked for', function () {
    foreach (CsrComparisonMetrics::keys() as $key) {
        expect(comparison($this->owner, $this->workspace, $key)->json('metric.key'))
            ->toBe($key);
    }
});

test('a request naming no metric, or one that does not exist, reads as total sales', function () {
    $ask = fn (string $query) => test()->actingAs($this->owner)->getJson(
        "/api/workspaces/{$this->workspace->slug}/csrs/stats/analytics-comparison?from=".CMP_FROM.'&to='.CMP_TO.$query
    )->json('metric.key');

    expect($ask(''))->toBe('total_sales');
    expect($ask('&metric=nonsense'))->toBe('total_sales');
    // And the keys the panel used to tab between still name a metric.
    expect($ask('&metric=call_time'))->toBe('total_rmo_call_time');
});

test('a column the panel never plotted before is a metric of its own', function () {
    $csr = cmpCsr('Mariel Bautista');

    cmpCallRow($this->workspace, $csr, '2026-08-16', [
        'total_verification_called' => 12,
        'total_verification_call_time' => 480,
    ]);
    cmpCallRow($this->workspace, $csr, '2026-08-09', ['total_verification_called' => 8]);

    $called = metricBlock(comparison($this->owner, $this->workspace, 'total_verification_called'));
    expect($called['format'])->toBe('number');
    expect($called['group'])->toBe('Calls');
    expect($called['rows'][0]['value'])->toEqual(12);
    // A count moves in per cent, not in points: 12 against 8.
    expect($called['rows'][0]['change'])->toEqual(50);

    expect(metricBlock(comparison($this->owner, $this->workspace, 'total_verification_call_time'))['format'])
        ->toBe('duration');
});

test('the longest call is the longest of the days, not their sum', function () {
    $csr = cmpCsr('Mariel Bautista');

    cmpCallRow($this->workspace, $csr, '2026-08-16', ['longest_rmo_call_time' => 320]);
    cmpCallRow($this->workspace, $csr, '2026-08-17', ['longest_rmo_call_time' => 180]);

    $longest = metricBlock(comparison($this->owner, $this->workspace, 'longest_rmo_call_time'));

    expect($longest['rows'][0]['value'])->toEqual(320);
});

test('the amounts and the parcel counts behind them are separate metrics', function () {
    $csr = cmpCsr('Angeline Mercado');

    cmpSettled($this->workspace, $csr, '2026-08-16 09:00:00', 250, true);
    cmpSettled($this->workspace, $csr, '2026-08-17 09:00:00', 750, false);

    $ask = fn (string $metric) => metricBlock(comparison($this->owner, $this->workspace, $metric));

    $returnedAmount = $ask('returning');
    expect($returnedAmount['format'])->toBe('currency');
    // Money back is the metric that reads better going down.
    expect($returnedAmount['higher_is_better'])->toBeFalse();
    expect($returnedAmount['rows'][0]['value'])->toEqual(250);

    $returnedParcels = $ask('returning_count');
    expect($returnedParcels['format'])->toBe('number');
    expect($returnedParcels['rows'][0]['value'])->toEqual(1);

    expect($ask('delivered')['rows'][0]['value'])->toEqual(750);
    expect($ask('delivered_count')['rows'][0]['value'])->toEqual(1);
});
