<?php

use App\Models\Product;
use App\Models\User;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Services\ProductIncomeStatementService;
use Modules\Finance\Services\UserIncomeStatementService;
use Modules\Finance\Services\UserProductIncomeStatementService;
use Modules\GencysERP\Models\GencysDailySalesOrder;
use Modules\GencysERP\Models\GencysDailySalesOrderItem;
use Modules\GencysERP\Models\Intern;
use Modules\Inventory\Models\InventoryUnitCode;

/**
 * OPEX is a company pool with nothing in it booked against one user or product,
 * so each slice row takes the share matching its delivered orders. These cover
 * that split on all three slices.
 *
 * The workspace's own OPEX figure is set straight onto the statement: how the
 * ledger arrives at it is IncomeStatementTest's business, not this file's.
 */
function opex_order(int $id, $workspace, string $cell, string $unitCode, array $attrs = []): void
{
    $order = GencysDailySalesOrder::create(array_merge([
        'id' => $id,
        'workspace_id' => $workspace->id,
        'intern_brands_name' => $cell,
        'parcel_status' => 'DELIVERED',
        'page' => 'FB Page',
        'platform' => 'Website',
        'parcel_updated_date' => '2026-05-12 09:00:00',
        'shipped_out_date' => '2026-05-06',
        'price_final' => 1000,
    ], $attrs));

    GencysDailySalesOrderItem::create(['order_id' => $order->id, 'sku' => $unitCode, 'quantity' => 1]);
}

function opex_seller($workspace, string $name): User
{
    $user = User::factory()->create(['name' => $name]);
    $workspace->users()->attach($user->id);

    Intern::create([
        'workspace_id' => $workspace->id,
        'intern_id' => crc32($name) % 100000,
        'full_name' => $name,
        'active' => true,
        'user_id' => $user->id,
    ]);

    return $user;
}

function opex_product($workspace, string $name, string $unitCode): Product
{
    $product = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => $name]);

    InventoryUnitCode::create([
        'workspace_id' => $workspace->id,
        'unit_code' => $unitCode,
        'product_id' => $product->id,
    ]);

    return $product;
}

function opex_statement($workspace, float $opex): IncomeStatement
{
    $workspace->update(['is_gencys_partner' => true]);

    return IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'opex' => $opex,
        'status' => 'final',
    ]);
}

test('the per-user statement splits OPEX by each user’s delivered orders', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    opex_product($workspace, 'WIDGET', 'UC1');
    $ana = opex_seller($workspace, 'Ana Reyes');
    $ben = opex_seller($workspace, 'Ben Cruz');

    // 3 delivered against 1 — a 75/25 split of the month's OPEX.
    opex_order(970001, $workspace, 'Ana Reyes', 'UC1');
    opex_order(970002, $workspace, 'Ana Reyes', 'UC1');
    opex_order(970003, $workspace, 'Ana Reyes', 'UC1');
    opex_order(970004, $workspace, 'Ben Cruz', 'UC1');

    $statement = opex_statement($workspace, 8000);
    app(UserIncomeStatementService::class)->snapshot($statement);

    $rows = $statement->userStatements()->get();

    expect((float) $rows->firstWhere('user_id', $ana->id)->opex)->toBe(6000.0)
        ->and((float) $rows->firstWhere('user_id', $ben->id)->opex)->toBe(2000.0)
        // Nothing of the pool goes missing.
        ->and(round((float) $rows->sum('opex'), 2))->toBe(8000.0);
});

test('the per-product statement splits OPEX by each product’s delivered orders', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $widget = opex_product($workspace, 'WIDGET', 'UC1');
    $gadget = opex_product($workspace, 'GADGET', 'UC2');
    opex_seller($workspace, 'Ana Reyes');

    opex_order(971001, $workspace, 'Ana Reyes', 'UC1');
    opex_order(971002, $workspace, 'Ana Reyes', 'UC1');
    opex_order(971003, $workspace, 'Ana Reyes', 'UC1');
    opex_order(971004, $workspace, 'Ana Reyes', 'UC2');

    $statement = opex_statement($workspace, 8000);
    app(ProductIncomeStatementService::class)->snapshot($statement);

    $rows = $statement->productStatements()->get();

    expect((float) $rows->firstWhere('product_id', $widget->id)->opex)->toBe(6000.0)
        ->and((float) $rows->firstWhere('product_id', $gadget->id)->opex)->toBe(2000.0)
        ->and(round((float) $rows->sum('opex'), 2))->toBe(8000.0);
});

test('net profit is gross after advisory, less that row’s OPEX', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    opex_product($workspace, 'WIDGET', 'UC1');
    $ana = opex_seller($workspace, 'Ana Reyes');

    opex_order(972001, $workspace, 'Ana Reyes', 'UC1', ['total_cog' => 200, 'shipping_fee' => 50]);

    $statement = opex_statement($workspace, 300);
    app(UserIncomeStatementService::class)->snapshot($statement);

    $row = $statement->userStatements()->where('user_id', $ana->id)->first();

    // The whole pool: one row carries all of it.
    expect((float) $row->opex)->toBe(300.0)
        ->and((float) $row->net_profit_delivered_cogs)
        ->toBe(round((float) $row->gross_profit_delivered_cogs_after_advisory_share - 300.0, 2))
        ->and((float) $row->net_profit_bought_cogs)
        ->toBe(round((float) $row->gross_profit_bought_cogs_after_advisory_share - 300.0, 2));
});

test('a row that delivered nothing carries no OPEX', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    opex_product($workspace, 'WIDGET', 'UC1');
    $ana = opex_seller($workspace, 'Ana Reyes');
    $idle = opex_seller($workspace, 'Idle Ivan');

    opex_order(973001, $workspace, 'Ana Reyes', 'UC1');

    $statement = opex_statement($workspace, 500);
    app(UserIncomeStatementService::class)->snapshot($statement);

    $rows = $statement->userStatements()->get();
    $idleRow = $rows->firstWhere('user_id', $idle->id);

    // OPEX follows parcels, so someone with none takes none of it — and the
    // whole pool still lands on the person who did deliver.
    expect($idleRow?->opex === null || (float) $idleRow->opex === 0.0)->toBeTrue()
        ->and((float) $rows->firstWhere('user_id', $ana->id)->opex)->toBe(500.0);
});

test('an uneven OPEX split loses no centavo', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    opex_product($workspace, 'WIDGET', 'UC1');
    opex_seller($workspace, 'Ana Reyes');
    opex_seller($workspace, 'Ben Cruz');
    opex_seller($workspace, 'Cara Lim');

    opex_order(974001, $workspace, 'Ana Reyes', 'UC1');
    opex_order(974002, $workspace, 'Ben Cruz', 'UC1');
    opex_order(974003, $workspace, 'Cara Lim', 'UC1');

    // 100.00 across three doesn't divide cleanly.
    $statement = opex_statement($workspace, 100);
    app(UserIncomeStatementService::class)->snapshot($statement);

    $shares = $statement->userStatements()->pluck('opex')->map(fn ($v) => (float) $v);

    expect($shares->sum())->toBe(100.0)
        ->and($shares->sort()->values()->all())->toBe([33.33, 33.33, 33.34]);
});

test('the cross statement closes out too, so the drill-down has no blanks', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    opex_product($workspace, 'WIDGET', 'UC1');
    $ana = opex_seller($workspace, 'Ana Reyes');
    opex_seller($workspace, 'Ben Cruz');

    opex_order(975001, $workspace, 'Ana Reyes', 'UC1');
    opex_order(975002, $workspace, 'Ana Reyes', 'UC1');
    opex_order(975003, $workspace, 'Ana Reyes', 'UC1');
    opex_order(975004, $workspace, 'Ben Cruz', 'UC1');

    $statement = opex_statement($workspace, 8000);
    app(UserProductIncomeStatementService::class)->snapshot($statement);

    $rows = $statement->userProductStatements()->get();

    expect((float) $rows->firstWhere('user_id', $ana->id)->opex)->toBe(6000.0)
        ->and(round((float) $rows->sum('opex'), 2))->toBe(8000.0);

    // And it reaches the drill-down page's payload.
    $payload = app(UserProductIncomeStatementService::class)->userPayload($statement, $ana->id);

    expect($payload['total']['opex'])->toBe(6000.0);
});

test('the share that produced each OPEX figure is saved beside it', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    opex_product($workspace, 'WIDGET', 'UC1');
    $ana = opex_seller($workspace, 'Ana Reyes');
    $ben = opex_seller($workspace, 'Ben Cruz');

    opex_order(976001, $workspace, 'Ana Reyes', 'UC1');
    opex_order(976002, $workspace, 'Ana Reyes', 'UC1');
    opex_order(976003, $workspace, 'Ana Reyes', 'UC1');
    opex_order(976004, $workspace, 'Ben Cruz', 'UC1');

    $statement = opex_statement($workspace, 8000);
    app(UserIncomeStatementService::class)->snapshot($statement);

    $rows = $statement->userStatements()->get();

    // A percentage (0-100), not a fraction — 3 of 4 delivered orders.
    expect((float) $rows->firstWhere('user_id', $ana->id)->opex_share_percentage)->toBe(75.0)
        ->and((float) $rows->firstWhere('user_id', $ben->id)->opex_share_percentage)->toBe(25.0)
        // The shares account for the whole pool.
        ->and(round((float) $rows->sum('opex_share_percentage'), 4))->toBe(100.0);
});

test('the saved share keeps enough precision for a single parcel', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    opex_product($workspace, 'WIDGET', 'UC1');
    $ana = opex_seller($workspace, 'Ana Reyes');
    $rare = opex_seller($workspace, 'Rare Rita');

    // 999 against 1 — a share of 0.1%, which two decimals would blur to 0.10
    // and no decimals would lose entirely.
    foreach (range(1, 999) as $i) {
        opex_order(977000 + $i, $workspace, 'Ana Reyes', 'UC1');
    }
    opex_order(978001, $workspace, 'Rare Rita', 'UC1');

    $statement = opex_statement($workspace, 10000);
    app(UserIncomeStatementService::class)->snapshot($statement);

    $rows = $statement->userStatements()->get();

    expect((float) $rows->firstWhere('user_id', $rare->id)->opex_share_percentage)->toBe(0.1)
        ->and((float) $rows->firstWhere('user_id', $ana->id)->opex_share_percentage)->toBe(99.9)
        ->and((float) $rows->firstWhere('user_id', $rare->id)->opex)->toBe(10.0);
});

test('a statement with nothing delivered saves a zero share, not a division by zero', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    opex_product($workspace, 'WIDGET', 'UC1');
    $ana = opex_seller($workspace, 'Ana Reyes');

    // Shipped inside the month but never delivered: the row exists and carries
    // shipping, but there are no delivered orders anywhere to take a ratio of.
    opex_order(979001, $workspace, 'Ana Reyes', 'UC1', [
        'parcel_status' => 'RTS',
        'parcel_updated_date' => null,
        'shipping_fee' => 40,
    ]);

    $statement = opex_statement($workspace, 5000);
    app(UserIncomeStatementService::class)->snapshot($statement);

    $row = $statement->userStatements()->where('user_id', $ana->id)->first();

    expect($row)->not->toBeNull()
        ->and((int) $row->delivered_orders)->toBe(0)
        ->and((int) $row->shipped_orders)->toBe(1)
        // No ratio to take, so the pool stays unallocated rather than landing
        // on a row that delivered nothing.
        ->and((float) $row->opex_share_percentage)->toBe(0.0)
        ->and((float) $row->opex)->toBe(0.0);
});
