<?php

use App\Models\Page;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Str;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Models\TransactionProduct;
use Modules\Finance\Models\TransactionType;
use Modules\Finance\Services\ProductIncomeStatementService;
use Modules\Finance\Services\UserProductIncomeStatementService;
use Modules\GencysERP\Models\GencysDailySalesOrder;
use Modules\GencysERP\Models\GencysDailySalesOrderItem;
use Modules\GencysERP\Models\Intern;
use Modules\Inventory\Models\InventoryUnitCode;
use Modules\Pancake\Models\Order as PancakeOrder;
use Modules\Products\Models\Product;

/** A delivered order written to an intern cell, carrying one unit code. */
function ups_order(int $id, $workspace, string $cell, string $unitCode, array $attrs = []): GencysDailySalesOrder
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
        'price_final' => 100,
    ], $attrs));

    GencysDailySalesOrderItem::create(['order_id' => $order->id, 'sku' => $unitCode, 'quantity' => 1]);

    return $order;
}

/** A seller: a user with an intern record whose cell the orders are written to. */
function ups_seller($workspace, string $name): User
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

/** A product with a unit code mapped to it. */
function ups_product($workspace, string $name, string $unitCode): Product
{
    $product = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => $name]);

    InventoryUnitCode::create([
        'workspace_id' => $workspace->id,
        'unit_code' => $unitCode,
        'product_id' => $product->id,
    ]);

    return $product;
}

/** A product-tagged outflow of the given transaction type. */
function ups_tagged_spend($workspace, Account $account, string $typeName, string $product, float $amount): void
{
    $type = TransactionType::firstOrCreate(
        ['workspace_id' => $workspace->id, 'name' => $typeName],
        ['income_statement_section' => 'cost_of_sales'],
    );

    $txn = Transaction::create([
        'workspace_id' => $workspace->id,
        'account_id' => $account->id,
        'date' => '2026-05-15',
        'description' => $typeName,
        'type' => 'out',
        'transaction_type_id' => $type->id,
        'amount' => $amount,
    ]);

    TransactionProduct::create([
        'transaction_id' => $txn->id,
        'product' => $product,
        'amount' => $amount,
    ]);
}

function ups_statement($workspace): IncomeStatement
{
    // These fixtures seed gencys orders, and the flag selects the source.
    $workspace->update(['is_gencys_partner' => true]);

    return IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'status' => 'final',
    ]);
}

function ups_snapshot(IncomeStatement $statement): void
{
    app(UserProductIncomeStatementService::class)->snapshot($statement);
}

test('a product run by two people gets a row each, with its own orders on each', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $product = ups_product($workspace, 'WIDGET', 'UC1');
    $ana = ups_seller($workspace, 'Ana Reyes');
    $ben = ups_seller($workspace, 'Ben Cruz');

    // Ana delivers three, Ben one.
    ups_order(950001, $workspace, 'Ana Reyes', 'UC1', ['price_final' => 500, 'total_cog' => 120]);
    ups_order(950002, $workspace, 'Ana Reyes', 'UC1', ['price_final' => 500, 'total_cog' => 120]);
    ups_order(950003, $workspace, 'Ana Reyes', 'UC1', ['price_final' => 500, 'total_cog' => 120]);
    ups_order(950004, $workspace, 'Ben Cruz', 'UC1', ['price_final' => 200, 'total_cog' => 40]);

    $statement = ups_statement($workspace);
    ups_snapshot($statement);

    $rows = $statement->userProductStatements()->get();

    $anaRow = $rows->firstWhere('user_id', $ana->id);
    $benRow = $rows->firstWhere('user_id', $ben->id);

    expect($anaRow->product_id)->toBe($product->id)
        ->and($anaRow->product_name)->toBe('WIDGET')
        ->and($anaRow->user_name)->toBe('Ana Reyes')
        // Order-carried figures are each seller's own — nothing apportioned.
        ->and((int) $anaRow->delivered_orders)->toBe(3)
        ->and((float) $anaRow->delivered_amount)->toBe(1500.0)
        ->and((float) $anaRow->total_delivered_cogs)->toBe(360.0)
        ->and((int) $benRow->delivered_orders)->toBe(1)
        ->and((float) $benRow->delivered_amount)->toBe(200.0)
        ->and((float) $benRow->total_delivered_cogs)->toBe(40.0);
});

test('a product cost is shared by each seller’s share of its delivered orders', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    ups_product($workspace, 'WIDGET', 'UC1');
    $ana = ups_seller($workspace, 'Ana Reyes');
    $ben = ups_seller($workspace, 'Ben Cruz');

    // 3 delivered against 1 — a 75/25 split of anything the product cost.
    ups_order(951001, $workspace, 'Ana Reyes', 'UC1');
    ups_order(951002, $workspace, 'Ana Reyes', 'UC1');
    ups_order(951003, $workspace, 'Ana Reyes', 'UC1');
    ups_order(951004, $workspace, 'Ben Cruz', 'UC1');

    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    ups_tagged_spend($workspace, $account, 'Cost of Goods', 'WIDGET', 4000);
    ups_tagged_spend($workspace, $account, 'Delivery Fee of COGS', 'WIDGET', 400);
    ups_tagged_spend($workspace, $account, 'Ad Spent', 'WIDGET', 800);

    $statement = ups_statement($workspace);
    ups_snapshot($statement);

    $rows = $statement->userProductStatements()->get();
    $anaRow = $rows->firstWhere('user_id', $ana->id);
    $benRow = $rows->firstWhere('user_id', $ben->id);

    expect((float) $anaRow->total_bought_cogs)->toBe(3000.0)
        ->and((float) $benRow->total_bought_cogs)->toBe(1000.0)
        ->and((float) $anaRow->total_bought_cogs_delivery_fee)->toBe(300.0)
        ->and((float) $benRow->total_bought_cogs_delivery_fee)->toBe(100.0)
        ->and((float) $anaRow->ad_spent)->toBe(600.0)
        ->and((float) $benRow->ad_spent)->toBe(200.0);
});

test('an uneven split loses no centavo — the shares add back to the cost', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    ups_product($workspace, 'WIDGET', 'UC1');
    ups_seller($workspace, 'Ana Reyes');
    ups_seller($workspace, 'Ben Cruz');
    ups_seller($workspace, 'Cara Lim');

    // One delivered order each: 100.00 across three doesn't divide cleanly.
    ups_order(952001, $workspace, 'Ana Reyes', 'UC1');
    ups_order(952002, $workspace, 'Ben Cruz', 'UC1');
    ups_order(952003, $workspace, 'Cara Lim', 'UC1');

    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    ups_tagged_spend($workspace, $account, 'Cost of Goods', 'WIDGET', 100);

    $statement = ups_statement($workspace);
    ups_snapshot($statement);

    $shares = $statement->userProductStatements()
        ->whereNotNull('user_id')->pluck('total_bought_cogs')
        ->map(fn ($a) => (float) $a);

    expect($shares->sum())->toBe(100.0)
        // The odd centavo goes to one row rather than vanishing.
        ->and($shares->sort()->values()->all())->toBe([33.33, 33.33, 33.34]);
});

test('a cost on a product nobody delivered stays whole on a row with no seller', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $widget = ups_product($workspace, 'WIDGET', 'UC1');
    ups_seller($workspace, 'Ana Reyes');

    // Ana delivered a different product; nothing of WIDGET went out this month.
    ups_product($workspace, 'GADGET', 'UC2');
    ups_order(953001, $workspace, 'Ana Reyes', 'UC2');

    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    ups_tagged_spend($workspace, $account, 'Cost of Goods', 'WIDGET', 5000);

    $statement = ups_statement($workspace);
    ups_snapshot($statement);

    $orphan = $statement->userProductStatements()
        ->where('product_id', $widget->id)->whereNull('user_id')->first();

    // Held here rather than dropped, or spread over sellers of other products.
    expect($orphan)->not->toBeNull()
        ->and((float) $orphan->total_bought_cogs)->toBe(5000.0)
        ->and((int) $orphan->delivered_orders)->toBe(0);
});

test('orders on an intern nobody is linked to land on a row with no seller', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $product = ups_product($workspace, 'WIDGET', 'UC1');
    ups_seller($workspace, 'Ana Reyes');

    ups_order(954001, $workspace, 'Ana Reyes', 'UC1', ['price_final' => 500]);
    ups_order(954002, $workspace, 'Ghost Intern', 'UC1', ['price_final' => 300]);

    $statement = ups_statement($workspace);
    ups_snapshot($statement);

    $unassigned = $statement->userProductStatements()
        ->where('product_id', $product->id)->whereNull('user_id')->first();

    expect($unassigned)->not->toBeNull()
        ->and($unassigned->user_name)->toBe('Unassigned')
        ->and((float) $unassigned->delivered_amount)->toBe(300.0);
});

test('summed over its sellers, a product matches its product-statement row', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $widget = ups_product($workspace, 'WIDGET', 'UC1');
    $gadget = ups_product($workspace, 'GADGET', 'UC2');
    ups_seller($workspace, 'Ana Reyes');
    ups_seller($workspace, 'Ben Cruz');

    ups_order(955001, $workspace, 'Ana Reyes', 'UC1', ['price_final' => 500, 'total_cog' => 120, 'shipping_fee' => 50]);
    ups_order(955002, $workspace, 'Ben Cruz', 'UC1', ['price_final' => 300, 'total_cog' => 80, 'shipping_fee' => 30]);
    ups_order(955003, $workspace, 'Ana Reyes', 'UC2', ['price_final' => 900, 'total_cog' => 200, 'shipping_fee' => 40]);
    // No product behind this code — the unresolved bucket, on both statements.
    ups_order(955004, $workspace, 'Ben Cruz', 'NOPE', ['price_final' => 70]);

    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    ups_tagged_spend($workspace, $account, 'Cost of Goods', 'WIDGET', 4000);
    ups_tagged_spend($workspace, $account, 'Delivery Fee of COGS', 'WIDGET', 175);
    ups_tagged_spend($workspace, $account, 'Ad Spent', 'GADGET', 999);

    $statement = ups_statement($workspace);
    ups_snapshot($statement);
    app(ProductIncomeStatementService::class)->snapshot($statement);

    $mine = $statement->userProductStatements()->get();

    $columns = [
        'delivered_orders', 'delivered_units', 'delivered_amount',
        'shipped_orders', 'total_shipping_fee', 'ad_spent',
        'cod_fee', 'cod_fee_vat', 'total_bought_cogs',
        'total_bought_cogs_delivery_fee', 'total_delivered_cogs',
    ];

    // This is the invariant the whole design rests on: the cross statement is
    // the product statement split by seller, so it has to add back up to it.
    foreach ($statement->productStatements()->get() as $product) {
        $sellers = $mine->where('product_id', $product->product_id);

        foreach ($columns as $column) {
            expect(round((float) $sellers->sum($column), 2))
                ->toBe(round((float) $product->$column, 2), "{$product->product_name}.{$column}");
        }
    }

    expect($mine->where('product_id', $widget->id))->toHaveCount(2)
        ->and($mine->where('product_id', $gadget->id))->toHaveCount(1);
});

test('rebuilding replaces the rows rather than stacking them up', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    ups_product($workspace, 'WIDGET', 'UC1');
    ups_seller($workspace, 'Ana Reyes');
    ups_order(956001, $workspace, 'Ana Reyes', 'UC1');

    $statement = ups_statement($workspace);
    ups_snapshot($statement);
    $first = $statement->userProductStatements()->count();

    ups_snapshot($statement);

    expect($statement->userProductStatements()->count())->toBe($first);
});

test('the page reads the saved rows, biggest product first with its sellers together', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    ups_product($workspace, 'WIDGET', 'UC1');
    ups_product($workspace, 'GADGET', 'UC2');
    ups_seller($workspace, 'Ana Reyes');
    ups_seller($workspace, 'Ben Cruz');

    // WIDGET outsells GADGET, and two people run it.
    ups_order(957001, $workspace, 'Ana Reyes', 'UC1', ['price_final' => 900]);
    ups_order(957002, $workspace, 'Ben Cruz', 'UC1', ['price_final' => 400]);
    ups_order(957003, $workspace, 'Ana Reyes', 'UC2', ['price_final' => 100]);

    $statement = ups_statement($workspace);
    ups_snapshot($statement);

    $this->actingAs($owner)
        ->get("/workspaces/{$workspace->slug}/finance/income-statements/{$statement->id}/user-products")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/finance/user-product-income-statements/index')
            ->where('rows', fn ($rows) => collect($rows)->pluck('product')->all() === [
                'WIDGET', 'WIDGET', 'GADGET',
            ])
            ->where('rows', fn ($rows) => collect($rows)->pluck('user')->all() === [
                'Ana Reyes', 'Ben Cruz', 'Ana Reyes',
            ])
            ->where('total.delivered_amount', fn ($v) => (float) $v === 1400.0)
        );
});

test('a non-member cannot read another workspace’s cross statement', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['user' => $outsider] = makeWorkspaceWithOwner();

    $statement = ups_statement($workspace);

    $this->actingAs($outsider)
        ->get("/workspaces/{$workspace->slug}/finance/income-statements/{$statement->id}/user-products")
        ->assertForbidden();
});

/**
 * The pancake side reaches both keys by join rather than by resolver, so it is
 * a separate code path with the same contract — worth its own case.
 */
test('a pancake workspace crosses page owner with shop product', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    // Not a gencys partner, so the pancake source is the one selected.
    $workspace->update(['is_gencys_partner' => false]);

    $widget = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);
    $ana = ups_seller($workspace, 'Ana Reyes');
    $ben = ups_seller($workspace, 'Ben Cruz');

    $shop = Shop::create(['workspace_id' => $workspace->id, 'name' => 'Shop', 'product_id' => $widget->id]);

    $pageFor = fn (User $owner, string $name) => Page::create([
        'workspace_id' => $workspace->id,
        'shop_id' => $shop->id,
        'name' => $name,
        'owner_id' => $owner->id,
    ]);

    $anaPage = $pageFor($ana, 'Ana Page');
    $benPage = $pageFor($ben, 'Ben Page');

    $order = fn (Page $page, float $amount) => PancakeOrder::create([
        'workspace_id' => $workspace->id,
        'order_number' => fake()->unique()->numerify('PC-#####'),
        'status' => 3,
        'status_name' => 'delivered',
        'shop_id' => $shop->id,
        'page_id' => $page->id,
        'customer_id' => (string) Str::uuid(),
        'inserted_at' => '2026-05-01 09:00:00',
        'final_amount' => $amount,
        'delivered_at' => '2026-05-10 09:00:00',
    ]);

    // Three of Ana's against one of Ben's — a 75/25 split of the product's cost.
    $order($anaPage, 500);
    $order($anaPage, 500);
    $order($anaPage, 500);
    $order($benPage, 200);

    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    ups_tagged_spend($workspace, $account, 'Cost of Goods', 'WIDGET', 4000);

    $statement = IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'status' => 'final',
    ]);
    ups_snapshot($statement);

    $rows = $statement->userProductStatements()->get();
    $anaRow = $rows->firstWhere('user_id', $ana->id);
    $benRow = $rows->firstWhere('user_id', $ben->id);

    expect($rows->whereNotNull('user_id'))->toHaveCount(2)
        // Both halves of the composite key survived the round trip.
        ->and($anaRow->product_id)->toBe($widget->id)
        ->and($benRow->product_id)->toBe($widget->id)
        ->and((int) $anaRow->delivered_orders)->toBe(3)
        ->and((float) $anaRow->delivered_amount)->toBe(1500.0)
        ->and((int) $benRow->delivered_orders)->toBe(1)
        ->and((float) $benRow->delivered_amount)->toBe(200.0)
        ->and((float) $anaRow->total_bought_cogs)->toBe(3000.0)
        ->and((float) $benRow->total_bought_cogs)->toBe(1000.0);
});

// --- the per-user drill-down ------------------------------------------------

function ups_drillUrl($workspace, IncomeStatement $statement, string $user): string
{
    return "/workspaces/{$workspace->slug}/finance/income-statements/{$statement->id}/users/{$user}";
}

test('drilling into a user shows a row per product they moved, biggest first', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    ups_product($workspace, 'WIDGET', 'UC1');
    ups_product($workspace, 'GADGET', 'UC2');
    $ana = ups_seller($workspace, 'Ana Reyes');
    $ben = ups_seller($workspace, 'Ben Cruz');

    ups_order(960001, $workspace, 'Ana Reyes', 'UC1', ['price_final' => 900]);
    ups_order(960002, $workspace, 'Ana Reyes', 'UC2', ['price_final' => 100]);
    // Ben's order must not appear on Ana's page.
    ups_order(960003, $workspace, 'Ben Cruz', 'UC1', ['price_final' => 400]);

    $statement = ups_statement($workspace);
    ups_snapshot($statement);

    $this->actingAs($owner)
        ->get(ups_drillUrl($workspace, $statement, (string) $ana->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/finance/user-income-statements/show')
            ->where('user.id', $ana->id)
            ->where('user.name', 'Ana Reyes')
            ->where('products', fn ($rows) => collect($rows)->pluck('product')->all() === ['WIDGET', 'GADGET'])
            // Ana's own revenue only — Ben's 400 on WIDGET is not hers.
            ->where('total.delivered_amount', fn ($v) => (float) $v === 1000.0)
        );

    // And Ben's page is his own.
    $this->actingAs($owner)
        ->get(ups_drillUrl($workspace, $statement, (string) $ben->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('products', fn ($rows) => collect($rows)->pluck('product')->all() === ['WIDGET'])
            ->where('total.delivered_amount', fn ($v) => (float) $v === 400.0)
        );
});

test('a shared product shows only this seller’s slice of its cost', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    ups_product($workspace, 'WIDGET', 'UC1');
    $ana = ups_seller($workspace, 'Ana Reyes');
    ups_seller($workspace, 'Ben Cruz');

    ups_order(961001, $workspace, 'Ana Reyes', 'UC1');
    ups_order(961002, $workspace, 'Ana Reyes', 'UC1');
    ups_order(961003, $workspace, 'Ana Reyes', 'UC1');
    ups_order(961004, $workspace, 'Ben Cruz', 'UC1');

    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    ups_tagged_spend($workspace, $account, 'Cost of Goods', 'WIDGET', 4000);

    $statement = ups_statement($workspace);
    ups_snapshot($statement);

    $this->actingAs($owner)
        ->get(ups_drillUrl($workspace, $statement, (string) $ana->id))
        ->assertOk()
        // Three of the product's four delivered orders are hers: 3,000 of 4,000.
        ->assertInertia(fn ($page) => $page
            ->where('total.total_bought_cogs', fn ($v) => (float) $v === 3000.0)
        );
});

test('the unassigned row drills in under its own path segment', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    ups_product($workspace, 'WIDGET', 'UC1');
    ups_seller($workspace, 'Ana Reyes');

    ups_order(962001, $workspace, 'Ana Reyes', 'UC1', ['price_final' => 500]);
    ups_order(962002, $workspace, 'Ghost Intern', 'UC1', ['price_final' => 300]);

    $statement = ups_statement($workspace);
    ups_snapshot($statement);

    $this->actingAs($owner)
        ->get(ups_drillUrl($workspace, $statement, 'unassigned'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('user.id', null)
            ->where('user.name', 'Unassigned')
            ->where('total.delivered_amount', fn ($v) => (float) $v === 300.0)
        );
});

test('a user the statement holds nothing for is a 404, as is a junk segment', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    ups_product($workspace, 'WIDGET', 'UC1');
    ups_seller($workspace, 'Ana Reyes');
    ups_order(963001, $workspace, 'Ana Reyes', 'UC1');

    $statement = ups_statement($workspace);
    ups_snapshot($statement);

    $this->actingAs($owner)->get(ups_drillUrl($workspace, $statement, '999999'))->assertNotFound();
    $this->actingAs($owner)->get(ups_drillUrl($workspace, $statement, 'nonsense'))->assertNotFound();
});

test('a non-member cannot drill into another workspace’s user', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['user' => $outsider] = makeWorkspaceWithOwner();

    ups_product($workspace, 'WIDGET', 'UC1');
    $ana = ups_seller($workspace, 'Ana Reyes');
    ups_order(965001, $workspace, 'Ana Reyes', 'UC1');

    $statement = ups_statement($workspace);
    ups_snapshot($statement);

    $this->actingAs($outsider)
        ->get(ups_drillUrl($workspace, $statement, (string) $ana->id))
        ->assertForbidden();
});

test('the drill-down shows the whole product and the share it was cut by', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    ups_product($workspace, 'WIDGET', 'UC1');
    $ana = ups_seller($workspace, 'Ana Reyes');
    ups_seller($workspace, 'Ben Cruz');

    // Ana 3 of the product's 4 delivered orders — a 75% share.
    ups_order(966001, $workspace, 'Ana Reyes', 'UC1');
    ups_order(966002, $workspace, 'Ana Reyes', 'UC1');
    ups_order(966003, $workspace, 'Ana Reyes', 'UC1');
    ups_order(966004, $workspace, 'Ben Cruz', 'UC1');

    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    ups_tagged_spend($workspace, $account, 'Cost of Goods', 'WIDGET', 4000);
    ups_tagged_spend($workspace, $account, 'Delivery Fee of COGS', 'WIDGET', 400);
    ups_tagged_spend($workspace, $account, 'Ad Spent', 'WIDGET', 800);

    $statement = ups_statement($workspace);
    ups_snapshot($statement);

    $this->actingAs($owner)
        ->get(ups_drillUrl($workspace, $statement, (string) $ana->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('products.0.whole_delivered_orders', 4)
            ->where('products.0.delivered_orders', 3)
            ->where('products.0.share', fn ($v) => abs((float) $v - 0.75) < 0.000001)
            // The product's whole cost, before it is split.
            ->where('products.0.whole_total_bought_cogs', fn ($v) => (float) $v === 4000.0)
            ->where('products.0.whole_total_bought_cogs_delivery_fee', fn ($v) => (float) $v === 400.0)
            // ...times the share, landing on what the row below it shows.
            ->where('products.0.total_bought_cogs', fn ($v) => (float) $v === 3000.0)
            ->where('products.0.total_bought_cogs_delivery_fee', fn ($v) => (float) $v === 300.0)
            ->where('products.0.ad_spent', fn ($v) => (float) $v === 600.0)
        );
});

test('whole x share lands on every allocated figure, across the products', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    ups_product($workspace, 'WIDGET', 'UC1');
    ups_product($workspace, 'GADGET', 'UC2');
    $ana = ups_seller($workspace, 'Ana Reyes');
    ups_seller($workspace, 'Ben Cruz');
    ups_seller($workspace, 'Cara Lim');

    // Deliberately awkward ratios so the rounding has somewhere to go.
    foreach (range(1, 7) as $i) {
        ups_order(967000 + $i, $workspace, 'Ana Reyes', 'UC1');
    }
    foreach (range(1, 3) as $i) {
        ups_order(967100 + $i, $workspace, 'Ben Cruz', 'UC1');
    }
    ups_order(967201, $workspace, 'Cara Lim', 'UC1');
    ups_order(967301, $workspace, 'Ana Reyes', 'UC2');
    ups_order(967302, $workspace, 'Ben Cruz', 'UC2');

    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    ups_tagged_spend($workspace, $account, 'Cost of Goods', 'WIDGET', 1000);
    ups_tagged_spend($workspace, $account, 'Ad Spent', 'GADGET', 333.33);

    $statement = ups_statement($workspace);
    ups_snapshot($statement);

    $payload = app(UserProductIncomeStatementService::class)->userPayload($statement, $ana->id);

    foreach ($payload['products'] as $row) {
        if ($row['share'] === null) {
            continue;
        }

        foreach (['total_bought_cogs', 'total_bought_cogs_delivery_fee'] as $figure) {
            $derived = round($row['whole_'.$figure] * $row['share'], 2);

            // Within a centavo: the split hands leftover centavos to the
            // largest remainders rather than rounding each share on its own.
            expect(abs($derived - $row[$figure]))->toBeLessThan(0.02,
                "{$row['product']}.{$figure}: whole {$row['whole_'.$figure]} x share {$row['share']} = {$derived}, row says {$row[$figure]}");
        }
    }

    // The Total column carries the same context across the products they run.
    expect($payload['total']['whole_delivered_orders'])->toBe(13)
        ->and($payload['total']['delivered_orders'])->toBe(8);
});
