<?php

use App\Jobs\Analytics\BuildPageOrderReportBreakdownJob;
use App\Models\Page;
use App\Models\Shop;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\OrderPhoneNumberReport;
use Modules\Pancake\Models\PageOrderReportBreakdownDailyRecord;

/**
 * The daily breakdown of orders by the customer history they arrived with.
 * An order lands on the day it was confirmed (confirmed_at) and is bucketed by
 * the (order_fail, order_success) pair of its 'initial' phone-number report.
 *
 * The rebuild itself is exercised through --sync, which runs the same builder
 * the queued job calls, so these assertions do not depend on queue config.
 */
function breakdownPage(Workspace $workspace): Page
{
    return Page::factory()->create([
        'workspace_id' => $workspace->id,
        'shop_id' => Shop::factory()->forWorkspace($workspace)->create()->id,
    ]);
}

function breakdownOrder(
    Workspace $workspace,
    Page $page,
    string $date,
    ?int $fail = null,
    ?int $success = null,
    string $type = 'initial',
    int $status = 1,
): Order {
    $order = Order::create([
        'workspace_id' => $workspace->id,
        'shop_id' => $page->shop_id,
        'page_id' => $page->id,
        'status' => $status,
        'status_name' => 'confirmed',
        'order_number' => (string) fake()->unique()->numberBetween(100000, 999999),
        'customer_id' => (string) Str::uuid(),
        // Placed a day earlier than it was confirmed, so any test that passes
        // would fail if the rollup ever went back to bucketing on inserted_at.
        'inserted_at' => CarbonImmutable::parse($date)->subDay()->setTime(9, 0)->toDateTimeString(),
        'confirmed_at' => $date.' 09:00:00',
    ]);

    if ($fail !== null || $success !== null) {
        OrderPhoneNumberReport::create([
            'order_id' => $order->id,
            'phone_number' => '09'.fake()->unique()->numerify('########'),
            'order_fail' => $fail ?? 0,
            'order_success' => $success ?? 0,
            'warning' => 0,
            'type' => $type,
        ]);
    }

    return $order;
}

/** Run the rebuild inline, the way a deploy backfill would. */
function rebuildBreakdown(array $options = []): void
{
    test()->artisan('build-page-order-report-breakdown-daily-records', $options + ['--sync' => true])
        ->assertSuccessful();
}

it('groups a day of orders into one row per customer history pair', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = breakdownPage($workspace);

    // Two orders from customers with the same history, one with a different pair.
    breakdownOrder($workspace, $page, '2026-03-10', fail: 1, success: 2);
    breakdownOrder($workspace, $page, '2026-03-10', fail: 1, success: 2);
    breakdownOrder($workspace, $page, '2026-03-10', fail: 0, success: 5);

    rebuildBreakdown(['--date' => '2026-03-10']);

    $rows = PageOrderReportBreakdownDailyRecord::where('workspace_id', $workspace->id)
        ->get()
        ->keyBy(fn ($row) => "{$row->order_fail}/{$row->order_success}");

    expect($rows)->toHaveCount(2)
        ->and($rows['1/2']->orders_count)->toBe(2)
        ->and($rows['1/2']->total_orders)->toBe(3)
        ->and($rows['0/5']->orders_count)->toBe(1)
        ->and($rows['0/5']->total_orders)->toBe(5)
        ->and($rows['1/2']->page_id)->toBe($page->id)
        ->and($rows['1/2']->shop_id)->toBe($page->shop_id);
});

it('reads the initial report and ignores the latest one', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = breakdownPage($workspace);

    // The same order carries both snapshots; only the locked one counts.
    $order = breakdownOrder($workspace, $page, '2026-03-10', fail: 1, success: 1);

    OrderPhoneNumberReport::create([
        'order_id' => $order->id,
        'phone_number' => '09'.fake()->unique()->numerify('########'),
        'order_fail' => 9,
        'order_success' => 9,
        'warning' => 0,
        'type' => 'latest',
    ]);

    rebuildBreakdown(['--date' => '2026-03-10']);

    $row = PageOrderReportBreakdownDailyRecord::where('workspace_id', $workspace->id)->sole();

    expect($row->order_fail)->toBe(1)
        ->and($row->order_success)->toBe(1)
        ->and($row->orders_count)->toBe(1);
});

it('separates the breakdown per page', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $pageA = breakdownPage($workspace);
    $pageB = breakdownPage($workspace);

    breakdownOrder($workspace, $pageA, '2026-03-10', fail: 1, success: 1);
    breakdownOrder($workspace, $pageB, '2026-03-10', fail: 1, success: 1);

    rebuildBreakdown(['--date' => '2026-03-10']);

    $rows = PageOrderReportBreakdownDailyRecord::where('workspace_id', $workspace->id)
        ->get()
        ->keyBy('page_id');

    expect($rows)->toHaveCount(2)
        ->and($rows[$pageA->id]->orders_count)->toBe(1)
        ->and($rows[$pageB->id]->orders_count)->toBe(1);
});

it('buckets an order by the day it was confirmed, not the day it was placed', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = breakdownPage($workspace);

    // Placed on the 10th, confirmed on the 11th.
    breakdownOrder($workspace, $page, '2026-03-11', fail: 0, success: 1);

    rebuildBreakdown(['--from' => '2026-03-10', '--to' => '2026-03-12']);

    $row = PageOrderReportBreakdownDailyRecord::where('workspace_id', $workspace->id)->sole();

    expect($row->date->toDateString())->toBe('2026-03-11');
});

it('excludes cancelled and removed orders', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = breakdownPage($workspace);

    breakdownOrder($workspace, $page, '2026-03-10', fail: 1, success: 1, status: 6);
    breakdownOrder($workspace, $page, '2026-03-10', fail: 1, success: 1, status: 7);
    breakdownOrder($workspace, $page, '2026-03-10', fail: 1, success: 1, status: 3);

    rebuildBreakdown(['--date' => '2026-03-10']);

    $row = PageOrderReportBreakdownDailyRecord::where('workspace_id', $workspace->id)->sole();

    expect($row->orders_count)->toBe(1);
});

it('ignores an order that was never confirmed', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = breakdownPage($workspace);

    breakdownOrder($workspace, $page, '2026-03-10', fail: 1, success: 1)
        ->update(['confirmed_at' => null]);

    rebuildBreakdown(['--from' => '2026-03-09', '--to' => '2026-03-11']);

    expect(PageOrderReportBreakdownDailyRecord::where('workspace_id', $workspace->id)->count())->toBe(0);
});

it('ignores orders that arrived without a phone number report', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = breakdownPage($workspace);

    breakdownOrder($workspace, $page, '2026-03-10');

    rebuildBreakdown(['--date' => '2026-03-10']);

    expect(PageOrderReportBreakdownDailyRecord::where('workspace_id', $workspace->id)->count())->toBe(0);
});

it('excludes orders whose customer had no prior history', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = breakdownPage($workspace);

    // 0/0 only ever reaches us carrying a warning — a real first-time customer
    // has no report row at all. Neither belongs in a history breakdown.
    breakdownOrder($workspace, $page, '2026-03-10', fail: 0, success: 0);
    breakdownOrder($workspace, $page, '2026-03-10', fail: 0, success: 1);

    rebuildBreakdown(['--date' => '2026-03-10']);

    $row = PageOrderReportBreakdownDailyRecord::where('workspace_id', $workspace->id)->sole();

    expect($row->total_orders)->toBe(1)
        ->and($row->order_success)->toBe(1)
        ->and($row->orders_count)->toBe(1);
});

it('drops a zero-history row left behind by an earlier build', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = breakdownPage($workspace);

    // A row written before the zero-history filter existed.
    PageOrderReportBreakdownDailyRecord::create([
        'workspace_id' => $workspace->id,
        'page_id' => $page->id,
        'shop_id' => $page->shop_id,
        'date' => '2026-03-10',
        'order_fail' => 0,
        'order_success' => 0,
        'total_orders' => 0,
        'orders_count' => 7,
    ]);

    rebuildBreakdown(['--date' => '2026-03-10']);

    expect(PageOrderReportBreakdownDailyRecord::where('workspace_id', $workspace->id)->count())->toBe(0);
});

it('rebuilds a day wholesale so re-running does not double count', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = breakdownPage($workspace);

    breakdownOrder($workspace, $page, '2026-03-10', fail: 1, success: 1);

    rebuildBreakdown(['--date' => '2026-03-10']);
    rebuildBreakdown(['--date' => '2026-03-10']);

    $row = PageOrderReportBreakdownDailyRecord::where('workspace_id', $workspace->id)->sole();

    expect($row->orders_count)->toBe(1);
});

it('limits the rebuild to one workspace when asked', function () {
    ['workspace' => $kept] = makeWorkspaceWithOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    breakdownOrder($kept, breakdownPage($kept), '2026-03-10', fail: 1, success: 1);
    breakdownOrder($other, breakdownPage($other), '2026-03-10', fail: 1, success: 1);

    rebuildBreakdown(['--date' => '2026-03-10', '--workspace' => $kept->id]);

    expect(PageOrderReportBreakdownDailyRecord::where('workspace_id', $kept->id)->count())->toBe(1)
        ->and(PageOrderReportBreakdownDailyRecord::where('workspace_id', $other->id)->count())->toBe(0);
});

it('fails rather than rebuilding everything when --workspace names nothing', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    breakdownOrder($workspace, breakdownPage($workspace), '2026-03-10', fail: 1, success: 1);

    $this->artisan('build-page-order-report-breakdown-daily-records', [
        '--date' => '2026-03-10',
        '--workspace' => 'no-such-workspace',
        '--sync' => true,
    ])->assertFailed();

    expect(PageOrderReportBreakdownDailyRecord::count())->toBe(0);
});

it('dispatches one job per workspace per day onto the analytics queue', function () {
    Queue::fake();

    ['workspace' => $a] = makeWorkspaceWithOwner();
    ['workspace' => $b] = makeWorkspaceWithOwner();

    $this->artisan('build-page-order-report-breakdown-daily-records', [
        '--from' => '2026-03-10',
        '--to' => '2026-03-11',
    ])->assertSuccessful();

    // 2 workspaces × 2 days.
    Queue::assertPushed(BuildPageOrderReportBreakdownJob::class, 4);

    Queue::assertPushed(
        BuildPageOrderReportBreakdownJob::class,
        fn (BuildPageOrderReportBreakdownJob $job) => $job->queue === 'analytics'
            && $job->workspaceId === $a->id
            && $job->date === '2026-03-10',
    );

    Queue::assertPushed(
        BuildPageOrderReportBreakdownJob::class,
        fn (BuildPageOrderReportBreakdownJob $job) => $job->workspaceId === $b->id
            && $job->date === '2026-03-11',
    );
});

it('rebuilds the slice when the job runs', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $page = breakdownPage($workspace);

    breakdownOrder($workspace, $page, '2026-03-10', fail: 2, success: 3);

    BuildPageOrderReportBreakdownJob::dispatchSync('2026-03-10', $workspace->id);

    $row = PageOrderReportBreakdownDailyRecord::where('workspace_id', $workspace->id)->sole();

    expect($row->orders_count)->toBe(1)
        ->and($row->order_fail)->toBe(2)
        ->and($row->order_success)->toBe(3)
        ->and($row->total_orders)->toBe(5);
});
