<?php

use App\Models\Order;
use App\Models\Workspace;

/**
 * The Orders list's exact order-number filter.
 *
 * Links that arrive already knowing which order they mean — the Order column on
 * the RTS call logs — carry `filter[order_number]` instead of `filter[search]`,
 * because search is a `like` across order number, tracking code, name, phone
 * and address, and an order number is short enough that it lands inside plenty
 * of them.
 */
function orderNumberRows(Workspace $workspace, array $filter): array
{
    return test()->get(route('workspaces.pancake.orders.index', [
        'workspace' => $workspace,
        'filter' => $filter,
    ]))->assertOk()->viewData('page')['props']['orders']['data'];
}

it('matches one order on the whole number, not on a number containing it', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    Order::factory()->forWorkspace($workspace)->create(['order_number' => '123']);
    Order::factory()->forWorkspace($workspace)->create(['order_number' => '1234']);
    Order::factory()->forWorkspace($workspace)->create(['order_number' => '9123']);

    $rows = orderNumberRows($workspace, ['order_number' => '123']);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['order_number'])->toBe('123');

    // The search box is unchanged: it still answers with all three.
    expect(orderNumberRows($workspace, ['search' => '123']))->toHaveCount(3);
});

it('stays inside the workspace', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    Order::factory()->forWorkspace($other)->create(['order_number' => '555']);

    expect(orderNumberRows($workspace, ['order_number' => '555']))->toHaveCount(0);
});
