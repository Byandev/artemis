<?php

use App\Models\Order;
use App\Models\Workspace;

/**
 * The Orders list's exact order-id filter.
 *
 * Links that arrive already knowing which order they mean — the Order column on
 * the RTS call logs — carry `filter[order_id]` instead of `filter[search]`,
 * because search is a `like` across order number, tracking code, name, phone
 * and address, and an id is short enough that it lands inside plenty of them.
 */
function orderIdRows(Workspace $workspace, array $filter): array
{
    return test()->get(route('workspaces.pancake.orders.index', [
        'workspace' => $workspace,
        'filter' => $filter,
    ]))->assertOk()->viewData('page')['props']['orders']['data'];
}

it('matches one order on the whole id, not on an id containing it', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $order = Order::factory()->forWorkspace($workspace)->create(['order_number' => '123']);
    Order::factory()->forWorkspace($workspace)->create([
        'order_number' => "{$order->id}4",
    ]);
    Order::factory()->forWorkspace($workspace)->create([
        'order_number' => "9{$order->id}",
    ]);

    $rows = orderIdRows($workspace, ['order_id' => $order->id]);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id'])->toBe($order->id);

    // The search box is unchanged: it still answers with all three.
    expect(orderIdRows($workspace, ['search' => (string) $order->id]))->toHaveCount(3);
});

it('stays inside the workspace', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $order = Order::factory()->forWorkspace($other)->create(['order_number' => '555']);

    expect(orderIdRows($workspace, ['order_id' => $order->id]))->toHaveCount(0);
});
