<?php

use App\Models\Product;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Models\TransactionProduct;
use Modules\Finance\Models\TransactionType;
use Modules\Finance\Services\ProductIncomeStatementService;
use Modules\GencysERP\Models\GencysDailySalesOrder;
use Modules\GencysERP\Models\GencysDailySalesOrderItem;
use Modules\Inventory\Models\InventoryUnitCode;

/** A delivered order, optionally with a single unit-code item on it. */
function pis_order(int $id, $workspace, string $cell, array $attrs, ?string $unitCode): GencysDailySalesOrder
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
    ], $attrs));

    if ($unitCode !== null) {
        GencysDailySalesOrderItem::create(['order_id' => $order->id, 'sku' => $unitCode, 'quantity' => 1]);
    }

    return $order;
}

function pis_statement($workspace): IncomeStatement
{
    return IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'status' => 'final',
    ]);
}

/** A product-tagged outflow of the given transaction type. */
function pis_tagged_spend($workspace, Account $account, string $typeName, string $product, float $amount): void
{
    $type = TransactionType::create([
        'workspace_id' => $workspace->id,
        'name' => $typeName,
        'income_statement_section' => 'cost_of_sales',
    ]);

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

test('the statement snapshots a product row per product, workspace-wide', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $product = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);
    InventoryUnitCode::create(['workspace_id' => $workspace->id, 'unit_code' => 'UC1', 'product_id' => $product->id]);

    // Two parcels from one intern and one from another — the product row counts
    // all three, whoever sold them. total_cog is the cost of what shipped.
    pis_order(870001, $workspace, 'Juan Dela Cruz', ['price_final' => 500, 'total_cog' => 120], 'UC1');
    pis_order(870002, $workspace, 'Juan Dela Cruz', ['price_final' => 500, 'total_cog' => 120], 'UC1');
    pis_order(870003, $workspace, 'Someone Else', ['price_final' => 300, 'total_cog' => 80], 'UC1');

    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    pis_tagged_spend($workspace, $account, 'Cost of Goods', 'WIDGET', 5000);
    pis_tagged_spend($workspace, $account, 'Delivery of COG', 'WIDGET', 250);
    pis_tagged_spend($workspace, $account, 'Ad Spent', 'WIDGET', 1800);

    $statement = pis_statement($workspace);
    app(ProductIncomeStatementService::class)->snapshot($statement);

    $row = $statement->productStatements()->where('product_id', $product->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->product_name)->toBe('WIDGET')
        // All three parcels, not just one intern's two.
        ->and((int) $row->delivered_orders)->toBe(3)
        ->and((int) $row->delivered_units)->toBe(3)
        ->and((float) $row->delivered_amount)->toBe(1300.0)
        // 2% of the delivered amount, and 12% of that fee — not of the amount.
        ->and((float) $row->cod_fee)->toBe(26.0)
        ->and((float) $row->cod_fee_vat)->toBe(3.12)
        // Tagged spend lands on the product it was tagged to.
        ->and((float) $row->ad_spent)->toBe(1800.0)
        ->and((float) $row->total_bought_cogs)->toBe(5000.0)
        ->and((float) $row->total_bought_cogs_delivery_fee)->toBe(250.0)
        // All three shipped out inside the month, with no fee recorded on them.
        ->and((int) $row->shipped_orders)->toBe(3)
        ->and((float) $row->total_shipping_fee)->toBe(0.0)
        // The cost of what actually shipped (120 + 120 + 80).
        ->and((float) $row->total_delivered_cogs)->toBe(320.0)
        // Both margins start from 1,300 less ad spend 1,800, shipping 0,
        // COD 26 and VAT 3.12 — then differ only in the cost of goods.
        // Delivered basis: 1300 − 1829.12 − 320 = −849.12
        ->and((float) $row->gross_profit_delivered_cogs)->toBe(-849.12)
        // Bought basis: 1300 − 1829.12 − 5000 − 250 = −5,779.12
        ->and((float) $row->gross_profit_bought_cogs)->toBe(-5779.12);

    // Rebuilding replaces the rows rather than stacking them up.
    app(ProductIncomeStatementService::class)->snapshot($statement);
    expect($statement->productStatements()->where('product_id', $product->id)->count())->toBe(1);
});

test('the product statement page reads the saved rows', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $widget = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);
    $gadget = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'GADGET']);
    InventoryUnitCode::create(['workspace_id' => $workspace->id, 'unit_code' => 'UC1', 'product_id' => $widget->id]);
    InventoryUnitCode::create(['workspace_id' => $workspace->id, 'unit_code' => 'UC2', 'product_id' => $gadget->id]);

    pis_order(880001, $workspace, 'Anyone', ['price_final' => 900, 'total_cog' => 200], 'UC1');
    pis_order(880002, $workspace, 'Anyone', ['price_final' => 100, 'total_cog' => 30], 'UC2');
    // No unit code behind this one — it lands in Unresolved, outside the Total.
    pis_order(880003, $workspace, 'Anyone', ['price_final' => 50, 'total_cog' => 10], 'NOPE');

    $statement = pis_statement($workspace);

    $this->actingAs($user)
        ->get("/workspaces/{$workspace->slug}/finance/income-statements/{$statement->id}/products")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/finance/product-income-statements/index')
            ->where('products', function ($products) {
                $rows = collect($products);

                return $rows->firstWhere('product', 'WIDGET')['delivered_amount'] == 900.0
                    && $rows->firstWhere('product', 'WIDGET')['total_delivered_cogs'] == 200.0
                    // Biggest delivered first, unresolved last.
                    && $rows->first()['product'] === 'WIDGET'
                    && $rows->last()['product_id'] === null;
            })
            // The Total covers the named products only — 900 + 100, not the 50.
            ->where('total.delivered_amount', fn ($v) => (float) $v === 1000.0)
            ->where('total.delivered_orders', 2)
            // 2% of 1,000, then 12% of that.
            ->where('total.cod_fee', fn ($v) => (float) $v === 20.0)
            ->where('total.cod_fee_vat', fn ($v) => (float) $v === 2.4)
            // The rates the columns are labelled with.
            ->where('rates.cod', fn ($v) => (float) $v === 0.02)
            ->where('rates.vat', fn ($v) => (float) $v === 0.12)
            // The Unresolved row can be opened up to show what's behind it.
            ->where('unresolved', fn ($rows) => collect($rows)->pluck('sku')->contains('NOPE'))
        );

    // The page built the snapshot on first view.
    expect($statement->productStatements()->count())->toBe(3);
});

test('an order carrying two products splits across both by quantity', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $widget = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);
    $gadget = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'GADGET']);
    InventoryUnitCode::create(['workspace_id' => $workspace->id, 'unit_code' => 'UC1', 'product_id' => $widget->id]);
    InventoryUnitCode::create(['workspace_id' => $workspace->id, 'unit_code' => 'UC2', 'product_id' => $gadget->id]);

    // One parcel, 800 pesos, 200 of goods — but 1 widget and 3 gadgets inside.
    $order = pis_order(890001, $workspace, 'Anyone', ['price_final' => 800, 'total_cog' => 200], null);
    GencysDailySalesOrderItem::create(['order_id' => $order->id, 'sku' => 'UC1', 'quantity' => 1]);
    GencysDailySalesOrderItem::create(['order_id' => $order->id, 'sku' => 'UC2', 'quantity' => 3]);

    $statement = pis_statement($workspace);
    app(ProductIncomeStatementService::class)->snapshot($statement);

    $row = fn (int $id) => $statement->productStatements()->where('product_id', $id)->first();

    // A quarter of the order is the widget, three quarters the gadget.
    expect((float) $row($widget->id)->delivered_amount)->toBe(200.0)
        ->and((float) $row($widget->id)->total_delivered_cogs)->toBe(50.0)
        ->and((float) $row($gadget->id)->delivered_amount)->toBe(600.0)
        ->and((float) $row($gadget->id)->total_delivered_cogs)->toBe(150.0)
        // The parcel counts once for each product it carries...
        ->and((int) $row($widget->id)->delivered_orders)->toBe(1)
        ->and((int) $row($gadget->id)->delivered_orders)->toBe(1)
        // ...but the pieces inside it are counted as they are.
        ->and((int) $row($widget->id)->delivered_units)->toBe(1)
        ->and((int) $row($gadget->id)->delivered_units)->toBe(3);

    // Nothing is created or lost splitting the order up.
    $all = $statement->productStatements()->get();
    expect(round($all->sum(fn ($r) => (float) $r->delivered_amount), 2))->toBe(800.0)
        ->and(round($all->sum(fn ($r) => (float) $r->total_delivered_cogs), 2))->toBe(200.0);
});

test('an item with no unit code behind it takes only its share to Unresolved', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $widget = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);
    InventoryUnitCode::create(['workspace_id' => $workspace->id, 'unit_code' => 'UC1', 'product_id' => $widget->id]);

    // Half the parcel is a mapped widget, half a sku nobody has mapped.
    $order = pis_order(890010, $workspace, 'Anyone', ['price_final' => 500, 'total_cog' => 100], null);
    GencysDailySalesOrderItem::create(['order_id' => $order->id, 'sku' => 'UC1', 'quantity' => 1]);
    GencysDailySalesOrderItem::create(['order_id' => $order->id, 'sku' => 'NOPE', 'quantity' => 1]);

    // An order with no items at all still has to land somewhere.
    pis_order(890011, $workspace, 'Anyone', ['price_final' => 90, 'total_cog' => 20], null);

    $statement = pis_statement($workspace);
    app(ProductIncomeStatementService::class)->snapshot($statement);

    $mapped = $statement->productStatements()->where('product_id', $widget->id)->first();
    $unresolved = $statement->productStatements()->whereNull('product_id')->first();

    expect((float) $mapped->delivered_amount)->toBe(250.0)
        // 250 from the unmapped half, plus the whole 90 order with no items.
        ->and((float) $unresolved->delivered_amount)->toBe(340.0)
        ->and((int) $unresolved->delivered_orders)->toBe(2);

    // 500 + 90, all still accounted for.
    expect(round($statement->productStatements()->get()->sum(fn ($r) => (float) $r->delivered_amount), 2))->toBe(590.0);
});

test('shipping is counted by ship-out date, not by delivery', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $product = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);
    InventoryUnitCode::create(['workspace_id' => $workspace->id, 'unit_code' => 'UC1', 'product_id' => $product->id]);

    // Shipped and delivered inside the month.
    pis_order(900001, $workspace, 'Anyone', [
        'price_final' => 500, 'shipping_fee' => 80,
        'shipped_out_date' => '2026-05-04', 'parcel_updated_date' => '2026-05-09 09:00:00',
    ], 'UC1');

    // Shipped this month but returned — the courier is still paid, so it counts
    // toward shipping while contributing nothing to delivered revenue.
    pis_order(900002, $workspace, 'Anyone', [
        'parcel_status' => 'RETURNED',
        'price_final' => 300, 'shipping_fee' => 70,
        'shipped_out_date' => '2026-05-06', 'parcel_updated_date' => '2026-05-20 09:00:00',
    ], 'UC1');

    // Shipped last month, delivered this one — revenue lands here, shipping doesn't.
    pis_order(900003, $workspace, 'Anyone', [
        'price_final' => 400, 'shipping_fee' => 60,
        'shipped_out_date' => '2026-04-28', 'parcel_updated_date' => '2026-05-02 09:00:00',
    ], 'UC1');

    $statement = pis_statement($workspace);
    app(ProductIncomeStatementService::class)->snapshot($statement);

    $row = $statement->productStatements()->where('product_id', $product->id)->first();

    // Delivered: the two that arrived this month (500 + 400).
    expect((int) $row->delivered_orders)->toBe(2)
        ->and((float) $row->delivered_amount)->toBe(900.0)
        // Shipped: the two that went out this month, returns included (80 + 70).
        ->and((int) $row->shipped_orders)->toBe(2)
        ->and((float) $row->total_shipping_fee)->toBe(150.0);
});

test('the unresolved breakdown explains the row it sits under', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $widget = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);
    InventoryUnitCode::create(['workspace_id' => $workspace->id, 'unit_code' => 'UC1', 'product_id' => $widget->id]);

    // A mapped parcel, for contrast — it must not appear in the breakdown.
    pis_order(910001, $workspace, 'Anyone', ['price_final' => 900, 'shipping_fee' => 40], 'UC1');

    // Two parcels on one unmapped code, one on another.
    pis_order(910002, $workspace, 'Anyone', ['price_final' => 300, 'shipping_fee' => 30], 'NOPE');
    pis_order(910003, $workspace, 'Anyone', ['price_final' => 200, 'shipping_fee' => 20], 'NOPE');
    pis_order(910004, $workspace, 'Anyone', ['price_final' => 100, 'shipping_fee' => 10], 'ALSO-NOPE');

    // Shipped this month but not delivered — it shows shipping and no revenue.
    pis_order(910005, $workspace, 'Anyone', [
        'parcel_status' => 'RETURNED',
        'price_final' => 500, 'shipping_fee' => 15,
        'shipped_out_date' => '2026-05-07', 'parcel_updated_date' => '2026-05-19 09:00:00',
    ], 'SHIPPED-ONLY');

    // An order with no line items at all still has to be accounted for.
    pis_order(910006, $workspace, 'Anyone', ['price_final' => 70, 'shipping_fee' => 5], null);

    $statement = pis_statement($workspace);
    $rows = collect(app(ProductIncomeStatementService::class)->unresolvedBreakdown($statement));

    // The mapped product is not part of this.
    expect($rows->pluck('sku'))->not->toContain('UC1');

    $nope = $rows->firstWhere('sku', 'NOPE');
    expect((int) $nope['delivered_orders'])->toBe(2)
        ->and((float) $nope['delivered_amount'])->toBe(500.0)
        ->and((float) $nope['shipping_fee'])->toBe(50.0)
        // Biggest delivered amount leads.
        ->and($rows->first()['sku'])->toBe('NOPE');

    // Shipped but never delivered: fee, no revenue.
    $shippedOnly = $rows->firstWhere('sku', 'SHIPPED-ONLY');
    expect((int) $shippedOnly['delivered_orders'])->toBe(0)
        ->and((float) $shippedOnly['delivered_amount'])->toBe(0.0)
        ->and((float) $shippedOnly['shipping_fee'])->toBe(15.0);

    // The itemless order comes back under a null sku.
    $noItems = $rows->firstWhere('sku', null);
    expect((float) $noItems['delivered_amount'])->toBe(70.0)
        ->and((int) $noItems['delivered_orders'])->toBe(1);

    // And the breakdown adds up to the Unresolved row it explains.
    app(ProductIncomeStatementService::class)->snapshot($statement);
    $unresolvedRow = $statement->productStatements()->whereNull('product_id')->first();

    expect(round($rows->sum('delivered_amount'), 2))->toBe((float) $unresolvedRow->delivered_amount)
        ->and(round($rows->sum('shipping_fee'), 2))->toBe((float) $unresolvedRow->total_shipping_fee);
});
