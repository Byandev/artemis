<?php

use App\Exports\CsrAnalyticsExport;
use App\Models\PancakeUserDailyCallReport;
use App\Models\PancakeUserPosDailyReport;
use App\Models\Shop;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * The Export button on the CSR breakdown.
 *
 * The sheet is the table being read, not a second opinion on it: it comes off
 * the same query, so the range, the search and the sort carry over — with the
 * paging taken off, since a spreadsheet of page one is no use. The columns are
 * whatever the reader has left visible in the table's own column menu.
 */
const EXP_FROM = '2026-08-01';
const EXP_TO = '2026-08-05';

beforeEach(function () {
    ['user' => $this->owner, 'workspace' => $this->workspace] = makeWorkspaceWithOwner();
    $this->shop = Shop::factory()->create(['workspace_id' => $this->workspace->id]);
});

/** A CSR on this workspace's shops, with a day of both rollups behind them. */
function expCsr(Workspace $workspace, int $shopId, string $name, array $pos = [], array $call = []): PancakeUser
{
    $csr = PancakeUser::create(['name' => $name]);

    // The pivot carries a uuid primary key of its own, hence the explicit insert.
    DB::table('pancake_shop_users')->insert([
        'id' => (string) Str::uuid(),
        'shop_id' => $shopId,
        'user_id' => $csr->id,
    ]);

    if ($pos !== []) {
        PancakeUserPosDailyReport::create([
            'workspace_id' => $workspace->id,
            'pancake_user_id' => $csr->id,
            'shop_id' => $shopId,
            'date' => '2026-08-02',
            ...$pos,
        ]);
    }

    if ($call !== []) {
        PancakeUserDailyCallReport::create([
            'workspace_id' => $workspace->id,
            'pancake_user_id' => $csr->id,
            'shop_id' => $shopId,
            'date' => '2026-08-02',
            ...$call,
        ]);
    }

    return $csr;
}

/** Hit the export and hand the export object to the expectations. */
function expDownload($owner, Workspace $workspace, array $query, Closure $assert): void
{
    Excel::fake();

    $params = http_build_query(['from' => EXP_FROM, 'to' => EXP_TO, ...$query]);

    test()->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/csr/analytics/export?{$params}")
        ->assertOk();

    Excel::assertDownloaded(
        'csr-analytics-'.EXP_FROM.'-to-'.EXP_TO.'-'.now()->format('His').'.xlsx',
        function ($export) use ($assert) {
            $assert($export->headings(), $export->query()->get()->map(
                fn ($row) => $export->map($row)
            )->all());

            return true;
        }
    );
}

test('it exports only the columns the table is showing, in the table order', function () {
    expCsr($this->workspace, $this->shop->id, 'Angeline Mercado', [
        'total_orders' => 3,
        'total_sales' => 6000,
    ], [
        'total_rmo_called' => 4,
        'total_rmo_call_time' => 300,
    ]);

    // Asked for out of order on purpose: the sheet follows the table's layout,
    // not the order the ids happened to arrive in.
    expDownload($this->owner, $this->workspace, [
        'columns' => 'total_call_time,total_sales,name,total_orders',
    ], function (array $headings, array $rows) {
        expect($headings)->toBe(['CSR', 'Orders', 'Sales', 'RMO Call Time (s)']);

        expect($rows)->toBe([['Angeline Mercado', 3, 6000.0, 300]]);
    });
});

test('the CSR name is written even when it was not among the columns asked for', function () {
    expCsr($this->workspace, $this->shop->id, 'Angeline Mercado', ['total_orders' => 3]);

    // A sheet of unattributed figures is not a report, so the name goes in front
    // of them whether or not the column menu had it on.
    expDownload($this->owner, $this->workspace, ['columns' => 'total_orders'], function (array $headings, array $rows) {
        expect($headings)->toBe(['CSR', 'Orders'])
            ->and($rows)->toBe([['Angeline Mercado', 3]]);
    });
});

test('asking for no columns exports the whole breakdown', function () {
    expCsr($this->workspace, $this->shop->id, 'Angeline Mercado', ['total_orders' => 3]);

    expDownload($this->owner, $this->workspace, [], function (array $headings) {
        expect($headings)->toBe(array_values(CsrAnalyticsExport::AVAILABLE_COLUMNS));
    });
});

test('the search and sort the table is under carry into the sheet', function () {
    expCsr($this->workspace, $this->shop->id, 'Angeline Mercado', ['total_sales' => 6000]);
    expCsr($this->workspace, $this->shop->id, 'Angelo Reyes', ['total_sales' => 9000]);
    expCsr($this->workspace, $this->shop->id, 'Mariel Bautista', ['total_sales' => 12000]);

    expDownload($this->owner, $this->workspace, [
        'filter' => ['search' => 'Angel'],
        'sort' => 'total_sales',
        'columns' => 'name',
    ], function (array $headings, array $rows) {
        // Mariel is filtered out; the two that match come back cheapest first,
        // the way the table was ordered.
        expect($rows)->toBe([['Angeline Mercado'], ['Angelo Reyes']]);
    });
});

test('every matching CSR is exported, not just the page on screen', function () {
    foreach (range(1, 25) as $i) {
        expCsr($this->workspace, $this->shop->id, "CSR {$i}", ['total_orders' => $i]);
    }

    // The table pages ten at a time; the sheet is the whole report.
    expDownload($this->owner, $this->workspace, [
        'per_page' => 10,
        'columns' => 'name',
    ], function (array $headings, array $rows) {
        expect($rows)->toHaveCount(25);
    });
});

test('a CSR who did nothing in the range is not a row in the sheet either', function () {
    expCsr($this->workspace, $this->shop->id, 'Angeline Mercado', ['total_orders' => 3]);
    expCsr($this->workspace, $this->shop->id, 'Cylde Dela Cruz');

    expDownload($this->owner, $this->workspace, ['columns' => 'name'], function (array $headings, array $rows) {
        expect($rows)->toBe([['Angeline Mercado']]);
    });
});

test('the download is a real spreadsheet, not just a query', function () {
    expCsr($this->workspace, $this->shop->id, 'Angeline Mercado', ['total_orders' => 3]);

    // Every test above fakes the writer, which is the only way to read the rows
    // back — so one run goes through it for real. The breakdown is built from
    // two joined subqueries, and Laravel Excel pages a FromQuery export with its
    // own limit/offset on top of that; this is what says the pair get along.
    $response = $this->actingAs($this->owner)->get(
        "/workspaces/{$this->workspace->slug}/csr/analytics/export?from=".EXP_FROM.'&to='.EXP_TO
    );

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('spreadsheetml');
    // A .xlsx is a zip archive, hence the local file header the body opens with.
    expect(substr($response->streamedContent(), 0, 2))->toBe('PK');
});

test('it refuses a member without the analytics permission', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get("/workspaces/{$this->workspace->slug}/csr/analytics/export")
        ->assertForbidden();
});
