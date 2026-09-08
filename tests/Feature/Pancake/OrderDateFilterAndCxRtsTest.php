<?php

use App\Models\Order;
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

    $counts = fn (array $filter) => ordersPage($workspace, $filter)->assertOk()
        ->viewData('page')['props']['statusCounts'];

    // The tab bar has to agree with the list, or the tabs promise rows that
    // aren't there once you click them.
    expect($counts(['date_from' => '2026-08-01', 'date_to' => '2026-08-31']))->toBe([])
        ->and($counts(['date_from' => '2026-08-01', 'date_to' => '2026-08-31', 'date_type' => 'confirmed_at']))
        ->toBe(['delivered' => 1]);
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
