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

/** A delivered order for the given intern cell, with a unit-code item. */
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

test('the statement snapshots a product row per product, workspace-wide', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $product = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);
    InventoryUnitCode::create(['workspace_id' => $workspace->id, 'unit_code' => 'UC1', 'product_id' => $product->id]);

    // Two parcels from Juan and one from another intern — the product row counts
    // all three, whoever sold them. total_cog is the cost of what shipped.
    pis_order(870001, $workspace, 'Juan Dela Cruz', ['price_final' => 500, 'total_cog' => 120], 'UC1');
    pis_order(870002, $workspace, 'Juan Dela Cruz', ['price_final' => 500, 'total_cog' => 120], 'UC1');
    pis_order(870003, $workspace, 'Someone Else', ['price_final' => 300, 'total_cog' => 80], 'UC1');

    // A bulk purchase of the goods, and the freight on it, both tagged to WIDGET.
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);

    $buy = function (string $typeName, float $amount) use ($workspace, $account) {
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
            'product' => 'WIDGET',
            'amount' => $amount,
        ]);
    };

    $buy('Cost of Goods', 5000);
    $buy('Delivery of COG', 250);

    $statement = IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'status' => 'final',
    ]);

    app(ProductIncomeStatementService::class)->snapshot($statement);

    $row = $statement->productStatements()->where('product_id', $product->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->product_name)->toBe('WIDGET')
        // All three parcels, not just Juan's two.
        ->and((int) $row->delivered_count)->toBe(3)
        ->and((float) $row->delivered_amount)->toBe(1300.0)
        // What was bought this month, kept apart from its freight...
        ->and((float) $row->total_bought_cogs)->toBe(5000.0)
        ->and((float) $row->total_bought_cogs_delivery_fee)->toBe(250.0)
        // ...and from the cost of what actually shipped (120 + 120 + 80).
        ->and((float) $row->total_delivered_cogs)->toBe(320.0);

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

    $statement = IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'status' => 'final',
    ]);

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
            ->where('total.delivered_count', 2)
            ->where('missingUnitCodes', fn ($codes) => collect($codes)->pluck('unit_code')->contains('NOPE'))
        );

    // The page built the snapshot on first view.
    expect($statement->productStatements()->count())->toBe(3);
});
