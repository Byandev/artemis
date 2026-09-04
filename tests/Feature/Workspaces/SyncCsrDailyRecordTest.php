<?php

use App\Jobs\SyncCsrDailyRecord;
use App\Models\Order;
use App\Models\PancakeUserPosDailyReport;
use App\Models\Shop;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * The nightly POS rollup, and specifically the parcel counts beside the money —
 * which the job computed but did not persist until CSR analytics needed them.
 */
beforeEach(function () {
    ['user' => $this->owner, 'workspace' => $this->workspace] = makeWorkspaceWithOwner();
    $this->csr = PancakeUser::create(['name' => 'Angeline Mercado']);
    // The rollup is keyed by shop, so orders meant to land on one row have to
    // share one — the factory would otherwise give each its own.
    $this->shop = Shop::factory()->create(['workspace_id' => $this->workspace->id]);
});

/** An order of $this->csr's, in the shared shop unless another is given. */
function posOrder(array $attributes, ?Shop $shop = null): Order
{
    $shop ??= test()->shop;

    return Order::factory()->forWorkspace(test()->workspace)->create([
        'shop_id' => $shop->id,
        'confirmed_by' => test()->csr->id,
        ...$attributes,
    ]);
}

function syncedRow($workspace, $csr, string $date): ?PancakeUserPosDailyReport
{
    (new SyncCsrDailyRecord($date))->handle();

    return PancakeUserPosDailyReport::query()
        ->where('workspace_id', $workspace->id)
        ->where('pancake_user_id', $csr->id)
        ->whereDate('date', $date)
        ->first();
}

test('the rollup keeps the parcel counts beside the amounts', function () {
    posOrder(['status' => 3, 'final_amount' => 800, 'delivered_at' => '2026-08-02 10:00:00']);
    posOrder(['status' => 4, 'final_amount' => 200, 'returning_at' => '2026-08-02 11:00:00']);

    $row = syncedRow($this->workspace, $this->csr, '2026-08-02');

    expect((float) $row->delivered)->toBe(800.0)
        ->and((int) $row->delivered_count)->toBe(1)
        ->and((float) $row->returning)->toBe(200.0)
        ->and((int) $row->returning_count)->toBe(1);
});

test('the rollup stores the day\'s return rate', function () {
    posOrder(['status' => 3, 'final_amount' => 8000, 'delivered_at' => '2026-08-02 10:00:00']);
    posOrder(['status' => 4, 'final_amount' => 2000, 'returning_at' => '2026-08-02 11:00:00']);

    // 2000 of the 10000 that settled. The CSR analytics RTS card reads this
    // column rather than rebuilding the rate from the two amounts.
    expect((float) syncedRow($this->workspace, $this->csr, '2026-08-02')->rts_rate)
        ->toBe(20.0);
});

test('a day with nothing settled stores no rate', function () {
    posOrder(['status' => 1, 'final_amount' => 5000, 'confirmed_at' => '2026-08-02 09:00:00']);

    // Zero, the column's default — the readers gate on the amounts, which is
    // what tells "no returns" apart from "nothing has landed yet".
    expect((float) syncedRow($this->workspace, $this->csr, '2026-08-02')->rts_rate)
        ->toBe(0.0);
});

test('a parcel worth nothing still counts as a parcel', function () {
    posOrder(['status' => 3, 'final_amount' => 0, 'delivered_at' => '2026-08-02 10:00:00']);

    $row = syncedRow($this->workspace, $this->csr, '2026-08-02');

    // The amount says nothing happened; the count keeps the CSR eligible.
    expect((float) $row->delivered)->toBe(0.0)
        ->and((int) $row->delivered_count)->toBe(1);
});

test('the counts follow the day the parcel settled, not the day it was taken', function () {
    posOrder([
        'status' => 3,
        'final_amount' => 500,
        'confirmed_at' => '2026-08-01 09:00:00',
        'delivered_at' => '2026-08-02 10:00:00',
    ]);

    $taken = syncedRow($this->workspace, $this->csr, '2026-08-01');
    $settled = syncedRow($this->workspace, $this->csr, '2026-08-02');

    // The 1st carries the order and the sale, no parcel.
    expect((int) $taken->total_orders)->toBe(1)
        ->and((int) $taken->delivered_count)->toBe(0);

    // The 2nd carries the parcel, and nothing taken.
    expect((int) $settled->delivered_count)->toBe(1)
        ->and((int) $settled->total_orders)->toBe(0);
});

/**
 * --workspace scopes a rebuild to one workspace.
 *
 * Without it every pass rewrites every workspace's day, which is right nightly
 * and wrong when you are re-running to fix one client's figures.
 */
test('a scoped rebuild leaves the other workspaces alone', function () {
    ['workspace' => $other] = makeWorkspaceWithOwner();
    $theirCsr = PancakeUser::create(['name' => 'Elsewhere CSR']);

    Order::factory()->forWorkspace($this->workspace)->create([
        'status' => 1,
        'final_amount' => 1000,
        'confirmed_at' => '2026-08-02 10:00:00',
        'confirmed_by' => $this->csr->id,
    ]);

    Order::factory()->forWorkspace($other)->create([
        'status' => 1,
        'final_amount' => 5000,
        'confirmed_at' => '2026-08-02 10:00:00',
        'confirmed_by' => $theirCsr->id,
    ]);

    (new SyncCsrDailyRecord('2026-08-02', 'POS', $this->workspace->id))->handle();

    expect(PancakeUserPosDailyReport::where('workspace_id', $this->workspace->id)->count())->toBe(1)
        ->and(PancakeUserPosDailyReport::where('workspace_id', $other->id)->count())->toBe(0);
});

test('an unscoped rebuild still covers every workspace', function () {
    ['workspace' => $other] = makeWorkspaceWithOwner();
    $theirCsr = PancakeUser::create(['name' => 'Elsewhere CSR']);

    Order::factory()->forWorkspace($this->workspace)->create([
        'status' => 1,
        'final_amount' => 1000,
        'confirmed_at' => '2026-08-02 10:00:00',
        'confirmed_by' => $this->csr->id,
    ]);

    Order::factory()->forWorkspace($other)->create([
        'status' => 1,
        'final_amount' => 5000,
        'confirmed_at' => '2026-08-02 10:00:00',
        'confirmed_by' => $theirCsr->id,
    ]);

    (new SyncCsrDailyRecord('2026-08-02'))->handle();

    expect(PancakeUserPosDailyReport::count())->toBe(2);
});

test('the command takes the workspace by slug or by id', function () {
    foreach ([$this->workspace->slug, (string) $this->workspace->id] as $option) {
        $this->artisan('sync:csr-daily-records', [
            '--date' => '2026-08-02',
            '--workspace' => $option,
        ])->assertSuccessful();
    }
});

test('the command refuses a workspace it cannot find rather than rebuilding them all', function () {
    $this->artisan('sync:csr-daily-records', [
        '--date' => '2026-08-02',
        '--workspace' => 'no-such-workspace',
    ])->assertFailed();
});

/**
 * The rollup's grain: one row per CSR per shop per day.
 *
 * Recorded this fine so a CSR's figures can be read per shop without going back
 * to the orders. Everything that reads the rollup groups by CSR, so the totals
 * it reports are unchanged — there are just more rows behind them.
 */
test('a CSR working two shops gets a row for each', function () {
    $second = Shop::factory()->create(['workspace_id' => $this->workspace->id]);

    posOrder(['status' => 1, 'final_amount' => 1000, 'confirmed_at' => '2026-08-02 10:00:00']);
    posOrder(['status' => 1, 'final_amount' => 400, 'confirmed_at' => '2026-08-02 11:00:00'], $second);

    (new SyncCsrDailyRecord('2026-08-02'))->handle();

    $rows = PancakeUserPosDailyReport::where('pancake_user_id', $this->csr->id)->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->firstWhere('shop_id', $this->shop->id)->total_sales)->toEqual(1000)
        ->and($rows->firstWhere('shop_id', $second->id)->total_sales)->toEqual(400)
        // Which is the figure the CSR breakdown table and the leader cards read.
        ->and((float) $rows->sum('total_sales'))->toBe(1400.0);
});

test('a CSR working one shop still gets one row', function () {
    posOrder(['status' => 1, 'final_amount' => 1000, 'confirmed_at' => '2026-08-02 10:00:00']);
    posOrder(['status' => 1, 'final_amount' => 400, 'confirmed_at' => '2026-08-02 11:00:00']);

    (new SyncCsrDailyRecord('2026-08-02'))->handle();

    $rows = PancakeUserPosDailyReport::where('pancake_user_id', $this->csr->id)->get();

    expect($rows)->toHaveCount(1)
        ->and((int) $rows->first()->shop_id)->toBe((int) $this->shop->id)
        ->and($rows->first()->total_sales)->toEqual(1400);
});

test('re-running the sync updates the rows rather than adding more', function () {
    $second = Shop::factory()->create(['workspace_id' => $this->workspace->id]);

    posOrder(['status' => 1, 'final_amount' => 1000, 'confirmed_at' => '2026-08-02 10:00:00']);
    posOrder(['status' => 1, 'final_amount' => 250, 'confirmed_at' => '2026-08-02 11:00:00'], $second);

    (new SyncCsrDailyRecord('2026-08-02'))->handle();
    (new SyncCsrDailyRecord('2026-08-02'))->handle();

    expect(PancakeUserPosDailyReport::count())->toBe(2)
        ->and((float) PancakeUserPosDailyReport::sum('total_sales'))->toBe(1250.0);
});
