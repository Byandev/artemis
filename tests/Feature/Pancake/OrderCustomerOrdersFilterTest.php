<?php

use App\Models\Order;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * The Orders list's Customer orders column and filter.
 *
 * How many orders the customer behind a row has ever placed, read off the
 * phone number's cumulative Pancake report rather than counted from our own
 * orders table — so a returning customer reads their whole history even on the
 * first order we synced for them.
 */
function cxOrdersPage(Workspace $workspace, array $filter = [])
{
    return test()->get(route('workspaces.pancake.orders.index', [
        'workspace' => $workspace,
        'filter' => $filter,
    ]));
}

/** @return array<int, array<string, mixed>> the rows the page rendered */
function cxOrdersRows(Workspace $workspace, array $filter = []): array
{
    return cxOrdersPage($workspace, $filter)->assertOk()
        ->viewData('page')['props']['orders']['data'];
}

/**
 * Give an order's customer a lifetime history: the per-order row that names
 * their phone number, and the cumulative report the count is read from.
 */
function cxHistory(Order $order, string $phone, int $fail, int $success, string $type = 'latest'): void
{
    DB::table('pancake_order_phone_number_reports')->insert([
        'order_id' => $order->id,
        'phone_number' => $phone,
        'order_fail' => $fail,
        'order_success' => $success,
        'type' => $type,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('pancake_phone_number_reports')->updateOrInsert(
        ['phone_number' => $phone],
        [
            'order_fail' => $fail,
            'order_success' => $success,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );
}

it('counts the customer failed and successful orders together', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $order = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'ORD-1']);
    $unknown = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'NONE']);

    cxHistory($order, '09170000001', fail: 3, success: 7);

    $totals = collect(cxOrdersRows($workspace))->pluck('cx_orders_total', 'order_number');

    expect((int) $totals['ORD-1'])->toBe(10)
        // No report is not a history of zero orders.
        ->and($totals['NONE'])->toBeNull();

    expect($unknown->order_number)->toBe('NONE');
});

it('reads the count off the cumulative report, not the order own snapshot', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $order = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'ORD-1']);

    cxHistory($order, '09170000001', fail: 1, success: 1);

    // The customer ordered again through a page we have not synced: the
    // cumulative report moves, the row beside this order does not.
    DB::table('pancake_phone_number_reports')
        ->where('phone_number', '09170000001')
        ->update(['order_success' => 9]);

    expect((int) cxOrdersRows($workspace)[0]['cx_orders_total'])->toBe(10);
});

it('counts a customer history once even when the order carries both snapshots', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $order = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'ORD-1']);

    // Both rows name the same number; joining both would double the history.
    cxHistory($order, '09170000001', fail: 1, success: 4, type: 'initial');
    cxHistory($order, '09170000001', fail: 2, success: 8, type: 'latest');

    expect((int) cxOrdersRows($workspace)[0]['cx_orders_total'])->toBe(10);
});

it('compares the order count against the number typed beside the operator', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $few = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'FEW']);
    $some = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'SOME']);
    $many = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'MANY']);
    Order::factory()->forWorkspace($workspace)->create(['order_number' => 'NONE']);

    cxHistory($few, '09170000001', fail: 1, success: 1);    // 2
    cxHistory($some, '09170000002', fail: 2, success: 8);   // 10
    cxHistory($many, '09170000003', fail: 10, success: 40); // 50

    $numbers = fn (string $operator, string $value) => collect(cxOrdersRows($workspace, [
        'customer_orders' => $value,
        'customer_orders_op' => $operator,
    ]))->pluck('order_number')->sort()->values()->all();

    expect($numbers('gt', '10'))->toBe(['MANY'])
        ->and($numbers('lt', '10'))->toBe(['FEW'])
        ->and($numbers('eq', '10'))->toBe(['SOME'])
        // An unknown customer answers no comparison at all — not even "< 10".
        ->and($numbers('lt', '100'))->toBe(['FEW', 'MANY', 'SOME']);
});

it('reads a between comparison as a band, whichever way round it is typed', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $few = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'FEW']);
    $some = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'SOME']);
    $many = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'MANY']);

    cxHistory($few, '09170000001', fail: 1, success: 1);    // 2
    cxHistory($some, '09170000002', fail: 2, success: 8);   // 10
    cxHistory($many, '09170000003', fail: 10, success: 40); // 50

    $numbers = fn (string $from, string $to) => collect(cxOrdersRows($workspace, [
        'customer_orders' => $from,
        'customer_orders_op' => 'between',
        'customer_orders_to' => $to,
    ]))->pluck('order_number')->sort()->values()->all();

    expect($numbers('5', '20'))->toBe(['SOME'])
        // Typed high-then-low is still the band the user meant.
        ->and($numbers('20', '5'))->toBe(['SOME'])
        // Inclusive at both ends, the way the boxes read.
        ->and($numbers('2', '10'))->toBe(['FEW', 'SOME']);
});

it('narrows nothing when the comparison is incomplete or unknown', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $order = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'ORD-1']);
    cxHistory($order, '09170000001', fail: 2, success: 8);

    // A blank box, a `between` missing its upper bound, and an operator that
    // isn't on the allowlist all leave the list alone rather than guessing.
    expect(cxOrdersRows($workspace, ['customer_orders' => '', 'customer_orders_op' => 'gt']))->toHaveCount(1)
        ->and(cxOrdersRows($workspace, ['customer_orders' => '5', 'customer_orders_op' => 'between']))->toHaveCount(1)
        ->and(cxOrdersRows($workspace, ['customer_orders' => '5', 'customer_orders_op' => 'DROP']))->toHaveCount(1);
});

it('narrows the status tab counts on the order count too', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $repeat = Order::factory()->forWorkspace($workspace)->create(['status_name' => 'delivered']);
    $first = Order::factory()->forWorkspace($workspace)->create(['status_name' => 'returned']);

    cxHistory($repeat, '09170000001', fail: 2, success: 8); // 10
    cxHistory($first, '09170000002', fail: 0, success: 1);  // 1

    $counts = fn (array $filter) => cxOrdersPage($workspace, $filter)->assertOk()
        ->viewData('page')['props']['statusCounts'];

    // The tab bar runs through the same filter set as the rows, or the tabs
    // promise rows that aren't there once you click them.
    expect($counts(['customer_orders' => '5', 'customer_orders_op' => 'gt']))->toBe(['delivered' => 1])
        ->and($counts(['customer_orders' => '5', 'customer_orders_op' => 'lt']))->toBe(['returned' => 1]);
});

it('sorts on the customer order count', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $few = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'FEW']);
    $many = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'MANY']);

    cxHistory($few, '09170000001', fail: 1, success: 1);
    cxHistory($many, '09170000002', fail: 10, success: 40);

    $sorted = test()->get(route('workspaces.pancake.orders.index', [
        'workspace' => $workspace,
        'sort' => '-cx_orders_total',
    ]))->assertOk()->viewData('page')['props']['orders']['data'];

    expect(collect($sorted)->pluck('order_number')->take(2)->all())->toBe(['MANY', 'FEW']);
});

it('adds up a customer who reaches an order under more than one number', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $order = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'ORD-1']);

    // Pancake reports an order by phone number, and an order can carry more than
    // one — the same way the Customer RTS rate sums across all of them.
    cxHistory($order, '09170000001', fail: 1, success: 1);
    cxHistory($order, '09170000002', fail: 2, success: 6);

    expect((int) cxOrdersRows($workspace)[0]['cx_orders_total'])->toBe(10);
});
