<?php

use App\Models\Order;
use App\Models\Page;
use App\Models\Workspace;
use App\Models\WorkspacePageDailyMetric;
use App\Support\Analytics\PageDailyMetricsBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function rebuildAndFetch(int $workspaceId, int $pageId, string $date): WorkspacePageDailyMetric
{
    app(PageDailyMetricsBuilder::class)->rebuild($workspaceId, $pageId, $date);

    return WorkspacePageDailyMetric::query()
        ->where('workspace_id', $workspaceId)
        ->where('page_id', $pageId)
        ->where('date', $date)
        ->firstOrFail();
}

test('rebuild produces zero row when no orders match the date', function () {
    $workspace = Workspace::factory()->create();
    $page = Page::factory()->forWorkspace($workspace)->create();

    $row = rebuildAndFetch($workspace->id, $page->id, '2026-04-01');

    expect($row->confirmed_count)->toBe(0)
        ->and($row->confirmed_amount)->toEqual('0.00')
        ->and($row->shipped_count)->toBe(0)
        ->and($row->delivered_count)->toBe(0)
        ->and($row->entered_returning_count)->toBe(0)
        ->and($row->returned_count)->toBe(0);
});

test('confirmed_count and confirmed_amount sum orders confirmed on the date', function () {
    $workspace = Workspace::factory()->create();
    $page = Page::factory()->forWorkspace($workspace)->create();

    Order::factory()->forPage($page)->create([
        'status' => 1,
        'confirmed_at' => '2026-04-15 10:00:00',
        'final_amount' => 500,
    ]);
    Order::factory()->forPage($page)->create([
        'status' => 3,
        'confirmed_at' => '2026-04-15 18:00:00',
        'final_amount' => 250,
    ]);
    Order::factory()->forPage($page)->create([
        'status' => 1,
        'confirmed_at' => '2026-04-14 23:59:59',
        'final_amount' => 999,
    ]);

    $row = rebuildAndFetch($workspace->id, $page->id, '2026-04-15');

    expect($row->confirmed_count)->toBe(2)
        ->and((float) $row->confirmed_amount)->toBe(750.0);
});

test('confirmed columns exclude status 6 and 7', function () {
    $workspace = Workspace::factory()->create();
    $page = Page::factory()->forWorkspace($workspace)->create();

    Order::factory()->forPage($page)->create([
        'status' => 1,
        'confirmed_at' => '2026-04-15 10:00:00',
        'final_amount' => 100,
    ]);
    Order::factory()->forPage($page)->create([
        'status' => 6,
        'confirmed_at' => '2026-04-15 11:00:00',
        'final_amount' => 200,
    ]);
    Order::factory()->forPage($page)->create([
        'status' => 7,
        'confirmed_at' => '2026-04-15 12:00:00',
        'final_amount' => 300,
    ]);

    $row = rebuildAndFetch($workspace->id, $page->id, '2026-04-15');

    expect($row->confirmed_count)->toBe(1)
        ->and((float) $row->confirmed_amount)->toBe(100.0);
});

test('shipped_count and shipped_amount sum orders shipped on the date', function () {
    $workspace = Workspace::factory()->create();
    $page = Page::factory()->forWorkspace($workspace)->create();

    Order::factory()->forPage($page)->create([
        'status' => 2,
        'shipped_at' => '2026-04-15 09:00:00',
        'confirmed_at' => '2026-04-14 09:00:00',
        'final_amount' => 400,
    ]);
    Order::factory()->forPage($page)->create([
        'status' => 6,
        'shipped_at' => '2026-04-15 09:00:00',
        'final_amount' => 999,
    ]);

    $row = rebuildAndFetch($workspace->id, $page->id, '2026-04-15');

    expect($row->shipped_count)->toBe(1)
        ->and((float) $row->shipped_amount)->toBe(400.0);
});

test('delivered_count and delivered_amount sum orders delivered on the date regardless of status', function () {
    $workspace = Workspace::factory()->create();
    $page = Page::factory()->forWorkspace($workspace)->create();

    Order::factory()->forPage($page)->create([
        'status' => 3,
        'delivered_at' => '2026-04-15 14:00:00',
        'final_amount' => 600,
    ]);
    Order::factory()->forPage($page)->create([
        'status' => 5,
        'delivered_at' => '2026-04-15 15:00:00',
        'returned_at' => '2026-04-20 10:00:00',
        'final_amount' => 200,
    ]);

    $row = rebuildAndFetch($workspace->id, $page->id, '2026-04-15');

    expect($row->delivered_count)->toBe(2)
        ->and((float) $row->delivered_amount)->toBe(800.0);
});

test('entered_returning counts orders that entered returning on the date regardless of subsequent return', function () {
    $workspace = Workspace::factory()->create();
    $page = Page::factory()->forWorkspace($workspace)->create();

    // Still in transit
    Order::factory()->forPage($page)->create([
        'status' => 4,
        'returning_at' => '2026-04-15 09:00:00',
        'returned_at' => null,
        'final_amount' => 300,
    ]);
    // Already completed return after entering returning on the date
    Order::factory()->forPage($page)->create([
        'status' => 5,
        'returning_at' => '2026-04-15 10:00:00',
        'returned_at' => '2026-04-22 10:00:00',
        'final_amount' => 200,
    ]);
    // Cancelled — should be excluded
    Order::factory()->forPage($page)->create([
        'status' => 6,
        'returning_at' => '2026-04-15 11:00:00',
        'final_amount' => 999,
    ]);

    $row = rebuildAndFetch($workspace->id, $page->id, '2026-04-15');

    expect($row->entered_returning_count)->toBe(2)
        ->and((float) $row->entered_returning_amount)->toBe(500.0);
});

test('returned_count and returned_amount sum orders returned on the date', function () {
    $workspace = Workspace::factory()->create();
    $page = Page::factory()->forWorkspace($workspace)->create();

    Order::factory()->forPage($page)->create([
        'status' => 5,
        'returned_at' => '2026-04-15 16:00:00',
        'final_amount' => 150,
    ]);
    Order::factory()->forPage($page)->create([
        'status' => 5,
        'returned_at' => '2026-04-14 23:59:59',
        'final_amount' => 999,
    ]);

    $row = rebuildAndFetch($workspace->id, $page->id, '2026-04-15');

    expect($row->returned_count)->toBe(1)
        ->and((float) $row->returned_amount)->toBe(150.0);
});

test('rebuild only considers orders for the given workspace and page', function () {
    $workspace = Workspace::factory()->create();
    $otherWorkspace = Workspace::factory()->create();
    $page = Page::factory()->forWorkspace($workspace)->create();
    $otherPage = Page::factory()->forWorkspace($workspace)->create();
    $otherWorkspacePage = Page::factory()->forWorkspace($otherWorkspace)->create();

    Order::factory()->forPage($page)->create([
        'status' => 1,
        'confirmed_at' => '2026-04-15 10:00:00',
        'final_amount' => 100,
    ]);
    Order::factory()->forPage($otherPage)->create([
        'status' => 1,
        'confirmed_at' => '2026-04-15 10:00:00',
        'final_amount' => 999,
    ]);
    Order::factory()->forPage($otherWorkspacePage)->create([
        'status' => 1,
        'confirmed_at' => '2026-04-15 10:00:00',
        'final_amount' => 999,
    ]);

    $row = rebuildAndFetch($workspace->id, $page->id, '2026-04-15');

    expect($row->confirmed_count)->toBe(1)
        ->and((float) $row->confirmed_amount)->toBe(100.0);
});

test('rebuild is idempotent — running twice yields the same row', function () {
    $workspace = Workspace::factory()->create();
    $page = Page::factory()->forWorkspace($workspace)->create();

    Order::factory()->forPage($page)->create([
        'status' => 1,
        'confirmed_at' => '2026-04-15 10:00:00',
        'final_amount' => 100,
    ]);

    rebuildAndFetch($workspace->id, $page->id, '2026-04-15');
    $row = rebuildAndFetch($workspace->id, $page->id, '2026-04-15');

    expect(WorkspacePageDailyMetric::query()->count())->toBe(1)
        ->and($row->confirmed_count)->toBe(1)
        ->and((float) $row->confirmed_amount)->toBe(100.0);
});
