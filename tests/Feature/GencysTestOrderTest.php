<?php

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Modules\GencysERP\Models\GencysDailySalesOrder;
use Modules\Inventory\Models\InventoryUnitCode;
use Modules\Inventory\Models\InventoryUnitCodeItem;

function testOrderUrl($workspace, string $path = ''): string
{
    return "/workspaces/{$workspace->slug}/gencys/daily-sales-tracker{$path}";
}

function makeUnitCode($workspace, string $code, string $itemCode, float $amount = 100): InventoryUnitCode
{
    $unitCode = InventoryUnitCode::create([
        'workspace_id' => $workspace->id,
        'unit_code' => $code,
        'sku' => "SKU-{$itemCode}",
        'total_amount' => $amount,
    ]);

    InventoryUnitCodeItem::create([
        'workspace_id' => $workspace->id,
        'unit_code' => $code,
        'item_code' => $itemCode,
        'quantity' => 1,
    ]);

    return $unitCode;
}

test('a test order derives order_details and total_qty from the picked unit codes', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    makeUnitCode($workspace, '2X MAGNERVE', 'MAG', 500);
    makeUnitCode($workspace, '1X HIKARIJOINT', 'HIK', 250);

    $this->actingAs($user)
        ->post(testOrderUrl($workspace), [
            'items' => [
                ['unit_code' => '2X MAGNERVE', 'quantity' => 2],
                ['unit_code' => '1X HIKARIJOINT', 'quantity' => 1],
            ],
            'parcel_status' => 'DELIVERED',
            'price_final' => 1250,
        ])
        ->assertRedirect();

    $order = GencysDailySalesOrder::sole();

    // Gencys' own "{qty}x{unit code}" format, rebuilt from the picks.
    expect($order->order_details)->toBe('2x2X MAGNERVE,1x1X HIKARIJOINT');
    expect($order->total_qty)->toBe(3);
    expect($order->id)->toBeGreaterThanOrEqual(GencysDailySalesOrder::TEST_ID_BASE);
    expect($order->is_test)->toBeTrue();

    // Each line lands in gencys_order_items keyed by the unit code, which is
    // what SyncInventoryFromGencysOrders expands into component items.
    expect($order->items()->pluck('quantity', 'sku')->all())->toBe([
        '2X MAGNERVE' => 2,
        '1X HIKARIJOINT' => 1,
    ]);
});

test('a delivered test order is picked up as income-statement revenue', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    makeUnitCode($workspace, 'CODE A', 'AAA');

    $this->actingAs($user)->post(testOrderUrl($workspace), [
        'items' => [['unit_code' => 'CODE A', 'quantity' => 1]],
        'parcel_status' => 'DELIVERED',
        'parcel_updated_date' => '2026-07-15 10:00:00',
        'price_final' => 1500,
        'total_cog' => 400,
    ]);

    // The same shape IncomeStatementController::deliveredRevenue() queries.
    $row = GencysDailySalesOrder::where('workspace_id', $workspace->id)
        ->where('parcel_status', 'DELIVERED')
        ->whereBetween('parcel_updated_date', ['2026-07-01 00:00:00', '2026-07-31 23:59:59'])
        ->selectRaw('COALESCE(SUM(price_final), 0) as delivered, COUNT(*) as orders')
        ->first();

    expect((float) $row->delivered)->toBe(1500.0);
    expect((int) $row->orders)->toBe(1);
});

test('an explicit total_qty is kept instead of the derived sum', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    makeUnitCode($workspace, 'CODE A', 'AAA');

    $this->actingAs($user)
        ->post(testOrderUrl($workspace), [
            'items' => [['unit_code' => 'CODE A', 'quantity' => 2]],
            'total_qty' => 9,
        ])
        ->assertRedirect();

    expect(GencysDailySalesOrder::sole()->total_qty)->toBe(9);
});

test('unit codes from another workspace are rejected', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    makeUnitCode($other, 'FOREIGN CODE', 'FOR');

    $this->actingAs($user)
        ->post(testOrderUrl($workspace), [
            'items' => [['unit_code' => 'FOREIGN CODE', 'quantity' => 1]],
        ])
        ->assertSessionHasErrors('items.0.unit_code');

    expect(GencysDailySalesOrder::count())->toBe(0);
});

test('at least one line item is required', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($user)
        ->post(testOrderUrl($workspace), ['items' => []])
        ->assertSessionHasErrors('items');
});

test('a test order can be deleted but a synced one cannot', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    makeUnitCode($workspace, 'CODE A', 'AAA');

    $this->actingAs($user)->post(testOrderUrl($workspace), [
        'items' => [['unit_code' => 'CODE A', 'quantity' => 1]],
    ]);

    $test = GencysDailySalesOrder::sole();

    // A real synced row: its id comes from Gencys, below the test range.
    $synced = GencysDailySalesOrder::create([
        'id' => 921462,
        'workspace_id' => $workspace->id,
    ]);

    $this->actingAs($user)
        ->delete(testOrderUrl($workspace, "/{$synced->id}"))
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(testOrderUrl($workspace, "/{$test->id}"))
        ->assertRedirect();

    expect(GencysDailySalesOrder::pluck('id')->all())->toBe([921462]);
    expect(DB::table('gencys_order_items')->count())->toBe(0);
});

test('the unit code options reach the page but not in production', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    makeUnitCode($workspace, 'CODE A', 'AAA');

    $this->actingAs($user)
        ->get(testOrderUrl($workspace))
        ->assertInertia(fn ($page) => $page
            ->where('canManageTestOrders', true)
            ->has('unitCodes', 1)
            ->where('unitCodes.0.unit_code', 'CODE A')
        );

    app()->detectEnvironment(fn () => 'production');

    $this->actingAs($user)
        ->get(testOrderUrl($workspace))
        ->assertInertia(fn ($page) => $page
            ->where('canManageTestOrders', false)
            ->where('unitCodes', [])
        );
});

test('storing is blocked in production', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    makeUnitCode($workspace, 'CODE A', 'AAA');

    app()->detectEnvironment(fn () => 'production');
    // Faking a production env also switches CSRF checks on, which would mask
    // the 404 we're actually asserting.
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $this->actingAs($user)
        ->post(testOrderUrl($workspace), [
            'items' => [['unit_code' => 'CODE A', 'quantity' => 1]],
        ])
        ->assertNotFound();

    expect(GencysDailySalesOrder::count())->toBe(0);
});
