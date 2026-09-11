<?php

use App\Models\Order;
use App\Models\ParcelJourney;
use App\Models\ShippingAddress;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Modules\Pancake\Support\CustomerRtsRisk;

/**
 * The Orders list's date filter and its Customer RTS column.
 *
 * The range used to be hard-wired to Pancake's insert time; it now points at
 * whichever date the page's dropdown names. And each row carries the customer's
 * own return history, banded, so a high-risk order is visible without opening it.
 */
function ordersPage(Workspace $workspace, array $filter = [])
{
    return test()->get(route('workspaces.pancake.orders.index', [
        'workspace' => $workspace,
        'filter' => $filter,
    ]));
}

/** @return array<int, array<string, mixed>> the rows the page rendered */
function orderRows(Workspace $workspace, array $filter = []): array
{
    return ordersPage($workspace, $filter)->assertOk()
        ->viewData('page')['props']['orders']['data'];
}

/**
 * The statuses of the rows a filter left standing — the shortest way to say
 * which orders survived it, now that the page no longer counts them per status.
 *
 * @return array<int, string>
 */
function statusesOn(Workspace $workspace, array $filter = []): array
{
    return collect(orderRows($workspace, $filter))->pluck('status_name')->all();
}

/** A phone-number report giving the order's customer a return history. */
function phoneReport(Order $order, int $fail, int $success, string $type = 'latest'): void
{
    DB::table('pancake_order_phone_number_reports')->insert([
        'order_id' => $order->id,
        'phone_number' => '09170000001',
        'order_fail' => $fail,
        'order_success' => $success,
        'type' => $type,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('filters the range on whichever date the dropdown names', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    // Inserted early, confirmed late. Which one the filter sees is the question.
    $order = Order::factory()->forWorkspace($workspace)->create([
        'order_number' => 'ORD-1',
        'inserted_at' => '2026-07-01 09:00:00',
        'confirmed_at' => '2026-08-15 09:00:00',
    ]);

    $july = ['date_from' => '2026-07-01', 'date_to' => '2026-07-31'];

    // Default is still inserted_at, so July finds it and August does not.
    expect(orderRows($workspace, $july))->toHaveCount(1)
        ->and(orderRows($workspace, ['date_from' => '2026-08-01', 'date_to' => '2026-08-31']))->toHaveCount(0);

    // Pointed at the confirmation date, the same two ranges swap answers.
    expect(orderRows($workspace, [...$july, 'date_type' => 'confirmed_at']))->toHaveCount(0)
        ->and(orderRows($workspace, ['date_from' => '2026-08-01', 'date_to' => '2026-08-31', 'date_type' => 'confirmed_at']))
        ->toHaveCount(1);

    expect($order->order_number)->toBe('ORD-1');
});

it('falls back to the insert date rather than trusting a column name off the request', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    Order::factory()->forWorkspace($workspace)->create([
        'inserted_at' => '2026-07-01 09:00:00',
        'confirmed_at' => '2026-08-15 09:00:00',
    ]);

    // Not on the allowlist — the filter behaves as if it were never sent.
    $rows = orderRows($workspace, [
        'date_from' => '2026-07-01',
        'date_to' => '2026-07-31',
        'date_type' => 'total_amount',
    ]);

    expect($rows)->toHaveCount(1);
});

it('narrows the status tab counts on the same date as the rows', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    Order::factory()->forWorkspace($workspace)->create([
        'status_name' => 'delivered',
        'inserted_at' => '2026-07-01 09:00:00',
        'confirmed_at' => '2026-08-15 09:00:00',
    ]);

    // August holds the confirmation but not the insert, so which column the
    // range is pointed at is the whole answer.
    expect(statusesOn($workspace, ['date_from' => '2026-08-01', 'date_to' => '2026-08-31']))->toBe([])
        ->and(statusesOn($workspace, ['date_from' => '2026-08-01', 'date_to' => '2026-08-31', 'date_type' => 'confirmed_at']))
        ->toBe(['delivered']);
});

it('bands each order by the customer own return rate', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $low = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'LOW']);
    $medium = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'MED']);
    $high = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'HIGH']);
    $unknown = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'NONE']);

    phoneReport($low, fail: 1, success: 9);      // 10%
    phoneReport($medium, fail: 3, success: 7);   // 30%
    phoneReport($high, fail: 7, success: 3);     // 70%

    $levels = collect(orderRows($workspace))->pluck('cx_rts_level', 'order_number');

    expect($levels['LOW'])->toBe(CustomerRtsRisk::LOW)
        ->and($levels['MED'])->toBe(CustomerRtsRisk::MEDIUM)
        ->and($levels['HIGH'])->toBe(CustomerRtsRisk::HIGH)
        // No report is its own answer — not the same as a clean record.
        ->and($levels['NONE'])->toBe(CustomerRtsRisk::NO_REPORT);

    expect($unknown->order_number)->toBe('NONE');
});

it('reads the latest report, not the one taken when the order came in', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $order = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'ORD-1']);

    phoneReport($order, fail: 0, success: 10, type: 'initial'); // clean back then
    phoneReport($order, fail: 8, success: 2, type: 'latest');   // 80% now

    expect(orderRows($workspace)[0]['cx_rts_level'])->toBe(CustomerRtsRisk::HIGH);
});

it('sorts on the customer return rate', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $low = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'LOW']);
    $high = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'HIGH']);

    phoneReport($low, fail: 1, success: 9);
    phoneReport($high, fail: 9, success: 1);

    $sorted = test()->get(route('workspaces.pancake.orders.index', [
        'workspace' => $workspace,
        'sort' => '-cx_rts_rate',
    ]))->assertOk()->viewData('page')['props']['orders']['data'];

    expect(collect($sorted)->pluck('order_number')->take(2)->all())->toBe(['HIGH', 'LOW']);
});

it('splits the list by whether the customer number has a report behind it', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $reported = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'WITH']);
    Order::factory()->forWorkspace($workspace)->create(['order_number' => 'NONE']);

    phoneReport($reported, fail: 3, success: 7);

    $numbers = fn (string $report) => collect(orderRows($workspace, ['report' => $report]))
        ->pluck('order_number')->all();

    expect($numbers('has_report'))->toBe(['WITH'])
        // The same set the row badges call "No report" — an unknown customer,
        // not a clean one.
        ->and($numbers('no_report'))->toBe(['NONE']);
});

it('compares the return rate against the percentage typed beside the operator', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $low = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'LOW']);
    $medium = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'MED']);
    $high = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'HIGH']);
    Order::factory()->forWorkspace($workspace)->create(['order_number' => 'NONE']);

    phoneReport($low, fail: 1, success: 9);      // 10%
    phoneReport($medium, fail: 3, success: 7);   // 30%
    phoneReport($high, fail: 7, success: 3);     // 70%

    $numbers = fn (string $operator, string $value) => collect(orderRows($workspace, [
        'report' => 'has_report',
        'rts_op' => $operator,
        'rts_value' => $value,
    ]))->pluck('order_number')->sort()->values()->all();

    expect($numbers('gt', '30'))->toBe(['HIGH'])
        ->and($numbers('lt', '30'))->toBe(['LOW'])
        // The rate is compared as a whole percent, so "= 30" catches the row
        // whose badge reads 30% rather than nothing at all.
        ->and($numbers('eq', '30'))->toBe(['MED'])
        // And the boundary belongs to both of the inclusive comparisons.
        ->and($numbers('gte', '30'))->toBe(['HIGH', 'MED'])
        ->and($numbers('lte', '30'))->toBe(['LOW', 'MED']);
});

it('keeps every reported order when the comparison is incomplete or unknown', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $order = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'WITH']);
    phoneReport($order, fail: 3, success: 7);

    // A blank box, and an operator that isn't on the allowlist, both narrow
    // nothing rather than dropping the report filter or reaching the SQL.
    expect(orderRows($workspace, ['report' => 'has_report', 'rts_op' => 'gt', 'rts_value' => '']))->toHaveCount(1)
        ->and(orderRows($workspace, ['report' => 'has_report', 'rts_op' => 'DROP', 'rts_value' => '30']))->toHaveCount(1);
});

it('narrows the status tab counts on the report filter too', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $reported = Order::factory()->forWorkspace($workspace)->create(['status_name' => 'delivered']);
    Order::factory()->forWorkspace($workspace)->create(['status_name' => 'returned']);

    phoneReport($reported, fail: 7, success: 3);

    expect(statusesOn($workspace, ['report' => 'has_report']))->toBe(['delivered'])
        ->and(statusesOn($workspace, ['report' => 'no_report']))->toBe(['returned'])
        ->and(statusesOn($workspace, ['report' => 'has_report', 'rts_op' => 'lt', 'rts_value' => '50']))->toBe([]);
});

it('reads a between comparison as a band, whichever way round it is typed', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $low = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'LOW']);
    $medium = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'MED']);
    $high = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'HIGH']);

    phoneReport($low, fail: 1, success: 9);      // 10%
    phoneReport($medium, fail: 3, success: 7);   // 30%
    phoneReport($high, fail: 7, success: 3);     // 70%

    $numbers = fn (string $from, string $to) => collect(orderRows($workspace, [
        'report' => 'has_report',
        'rts_op' => 'between',
        'rts_value' => $from,
        'rts_value2' => $to,
    ]))->pluck('order_number')->sort()->values()->all();

    expect($numbers('20', '50'))->toBe(['MED'])
        // Typed high-then-low is still the band the user meant; BETWEEN taken
        // literally would match nothing at all.
        ->and($numbers('50', '20'))->toBe(['MED'])
        // Inclusive at both ends, the way the boxes read.
        ->and($numbers('10', '30'))->toBe(['LOW', 'MED']);
});

it('ignores a between comparison that is missing its upper bound', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $order = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'WITH']);
    phoneReport($order, fail: 7, success: 3);

    // Half a band narrows nothing rather than guessing an end for it.
    expect(orderRows($workspace, [
        'report' => 'has_report',
        'rts_op' => 'between',
        'rts_value' => '20',
    ]))->toHaveCount(1);
});

it('no longer offers this app own row stamps as date fields', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $fields = ordersPage($workspace)->assertOk()
        ->viewData('page')['props']['dateFields'];

    // created_at / updated_at say when the sync last touched our row, which is
    // a question about the sync rather than about the order.
    expect($fields)->not->toContain('created_at')
        ->and($fields)->not->toContain('updated_at')
        ->and($fields)->toContain('inserted_at');
});

it('falls back to the insert date when asked for a stamp it no longer offers', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    // Inserted in July on Pancake's side; synced into our table today.
    Order::factory()->forWorkspace($workspace)->create([
        'inserted_at' => '2026-07-01 09:00:00',
        'created_at' => now(),
    ]);

    // Honouring created_at would put the order outside July and return nothing;
    // dropped from the allowlist, the range falls back to inserted_at.
    $rows = orderRows($workspace, [
        'date_from' => '2026-07-01',
        'date_to' => '2026-07-31',
        'date_type' => 'created_at',
    ]);

    expect($rows)->toHaveCount(1);
});

it('narrows on one status, or on several at once', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    Order::factory()->forWorkspace($workspace)->create(['status_name' => 'delivered']);
    Order::factory()->forWorkspace($workspace)->create(['status_name' => 'returned']);
    Order::factory()->forWorkspace($workspace)->create(['status_name' => 'shipped']);

    // Spatie splits the list apart before the filter sees it, which is what
    // lets the Status row name more than one at a time.
    expect(statusesOn($workspace, ['status' => 'delivered']))->toBe(['delivered'])
        ->and(statusesOn($workspace, ['status' => 'delivered,returned']))
        ->toEqualCanonicalizing(['delivered', 'returned']);
});

it('narrows on the search box', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    Order::factory()->forWorkspace($workspace)->create([
        'order_number' => 'KEEP-1',
        'status_name' => 'delivered',
    ]);
    Order::factory()->forWorkspace($workspace)->create([
        'order_number' => 'DROP-1',
        'status_name' => 'returned',
    ]);

    expect(statusesOn($workspace, ['search' => 'KEEP']))->toBe(['delivered']);
});

it('searches on a phrase containing a comma', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $match = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'KEEP-1']);
    Order::factory()->forWorkspace($workspace)->create(['order_number' => 'DROP-1']);

    ShippingAddress::create([
        'order_id' => $match->id,
        'full_name' => 'Juan Dela Cruz',
        'phone_number' => '09170000001',
        'full_address' => '12 Mabini St, Makati',
    ]);

    // Spatie splits a filter value on commas before the filter ever sees it, so
    // an address typed whole arrives as ['12 Mabini St', ' Makati'] and has to
    // be put back together to match anything.
    $rows = orderRows($workspace, ['search' => '12 Mabini St, Makati']);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['order_number'])->toBe('KEEP-1');
});

it('matches a rider whose name contains a comma', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $match = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'KEEP-1']);
    $other = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'DROP-1']);

    ParcelJourney::create([
        'order_id' => $match->id,
        'status' => 'On Delivery',
        'rider_name' => 'Dela Cruz, Juan',
        'rider_mobile' => '09170000001',
        'note' => '',
    ]);
    ParcelJourney::create([
        'order_id' => $other->id,
        'status' => 'On Delivery',
        'rider_name' => 'Santos, Pedro',
        'rider_mobile' => '09170000002',
        'note' => '',
    ]);

    $rows = orderRows($workspace, ['rider' => 'Dela Cruz, Juan']);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['order_number'])->toBe('KEEP-1');
});

it('accepts a comma-separated list of statuses', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    Order::factory()->forWorkspace($workspace)->create(['status_name' => 'returning']);
    Order::factory()->forWorkspace($workspace)->create(['status_name' => 'returned']);
    Order::factory()->forWorkspace($workspace)->create(['status_name' => 'delivered']);

    // How the RTS pages link into the list.
    expect(orderRows($workspace, ['status' => 'returning,returned']))->toHaveCount(2);
});
