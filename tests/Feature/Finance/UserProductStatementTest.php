<?php

use App\Models\Product;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Models\TransactionProduct;
use Modules\Finance\Models\TransactionType;
use Modules\Finance\Services\UserIncomeStatementService;
use Modules\GencysERP\Models\GencysDailySalesOrder;
use Modules\GencysERP\Models\GencysDailySalesOrderItem;
use Modules\GencysERP\Models\Intern;
use Modules\Inventory\Models\InventoryUnitCode;

/** A delivered order for the given intern cell, with a unit-code item. */
function ups_order(int $id, $workspace, string $cell, array $attrs, ?string $unitCode): GencysDailySalesOrder
{
    $order = GencysDailySalesOrder::create(array_merge([
        'id' => $id,
        'workspace_id' => $workspace->id,
        'intern_brands_name' => $cell,
        'parcel_status' => 'DELIVERED',
        'page' => 'FB Page',        // non-null so the `page NOT LIKE %pikutin%` filter keeps it
        'platform' => 'Website',
        'parcel_updated_date' => '2026-05-12 09:00:00',
        'shipped_out_date' => '2026-05-06',
    ], $attrs));

    if ($unitCode !== null) {
        GencysDailySalesOrderItem::create(['order_id' => $order->id, 'sku' => $unitCode, 'quantity' => 1]);
    }

    return $order;
}

test('the per-product statement resolves the product through unit-code order items', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $intern = Intern::create([
        'workspace_id' => $workspace->id,
        'intern_id' => 1,
        'full_name' => 'Juan Dela Cruz',
        'active' => true,
        'user_id' => $user->id,
    ]);

    $product = Product::factory()->create([
        'workspace_id' => $workspace->id,
        'name' => 'WIDGET',
    ]);

    // Unit code UC1 maps to WIDGET; the order item's sku carries that unit code.
    InventoryUnitCode::create([
        'workspace_id' => $workspace->id,
        'unit_code' => 'UC1',
        'product_id' => $product->id,
    ]);

    // A mapped order (→ WIDGET) and an unmapped one (item sku matches no unit code).
    ups_order(800001, $workspace, 'Juan Dela Cruz', [
        'price_final' => 1000, 'total_cog' => 200, 'shipping_fee' => 50,
    ], 'UC1');
    ups_order(800002, $workspace, 'Juan Dela Cruz', [
        'price_final' => 500, 'total_cog' => 100, 'shipping_fee' => 0,
    ], 'NOPE');

    $statement = IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'status' => 'final',
    ]);

    $service = app(UserIncomeStatementService::class);
    $products = collect($service->userPayload($statement, $user)['products']);

    // WIDGET: 1000 − (ship 50 + COD 20 + VAT 2.40) = 927.60. The order's
    // `total_cog` is ignored — COGS comes in as a transaction instead.
    $widget = $products->firstWhere('product', 'WIDGET');
    expect($widget)->not->toBeNull()
        ->and($widget['product_id'])->toBe($product->id)
        ->and((float) $widget['delivered'])->toBe(1000.0)
        ->and((float) $widget['cost_of_sales'])->toBe(72.40)
        ->and((float) $widget['gross_profit'])->toBe(927.60)
        ->and((int) $widget['orders'])->toBe(1);

    // The order whose item resolves to no product lands in "Discrepancy", last.
    expect($products->last()['product'])->toBe('Discrepancy');

    $discrepancy = $products->firstWhere('product', 'Discrepancy');
    expect($discrepancy)->not->toBeNull()
        ->and($discrepancy['product_id'])->toBeNull()
        ->and((float) $discrepancy['delivered'])->toBe(500.0)
        // 500 − (ship 0 + COD 10 + VAT 1.20) = 488.80
        ->and((float) $discrepancy['gross_profit'])->toBe(488.80);

    // The unresolved unit code surfaces in the missing-unit-codes warning list;
    // the mapped one does not.
    $missing = collect($service->missingUnitCodes($statement))->pluck('unit_code');
    expect($missing)->toContain('NOPE')
        ->and($missing)->not->toContain('UC1');
});

/** The per-user statement page, seeded with one mapped product + one unmapped order. */
function ups_seed(): array
{
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    Intern::create([
        'workspace_id' => $workspace->id,
        'intern_id' => 1,
        'full_name' => 'Juan Dela Cruz',
        'active' => true,
        'user_id' => $user->id,
    ]);

    $product = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);

    InventoryUnitCode::create([
        'workspace_id' => $workspace->id,
        'unit_code' => 'UC1',
        'product_id' => $product->id,
    ]);

    ups_order(810001, $workspace, 'Juan Dela Cruz', [
        'price_final' => 1000, 'shipping_fee' => 50,
    ], 'UC1');
    ups_order(810002, $workspace, 'Juan Dela Cruz', [
        'price_final' => 500, 'shipping_fee' => 0,
    ], 'NOPE');

    $statement = IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'status' => 'final',
    ]);

    return [$user, $workspace, $statement, $product];
}

test('the per-user statement page carries the product breakdown', function () {
    [$user, $workspace, $statement, $product] = ups_seed();

    $this->actingAs($user)
        ->get("/workspaces/{$workspace->slug}/finance/income-statements/{$statement->id}/users/{$user->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/finance/income-statements/show')
            ->where('scope.label', $user->name)
            // The commission endpoint the breakdown's rate input posts to.
            ->where('commissionUrl', "/workspaces/{$workspace->slug}/finance/income-statements/{$statement->id}/users/{$user->id}/commission-rate")
            ->where('statement.products', fn ($products) => collect($products)->firstWhere('product', 'WIDGET') !== null
                && (float) collect($products)->firstWhere('product', 'WIDGET')['delivered'] === 1000.0
                && (float) collect($products)->firstWhere('product', 'WIDGET')['commission_rate'] === 0.0
                // The unresolved order is kept, last, as "Discrepancy".
                && collect($products)->last()['product'] === 'Discrepancy')
        );
});

test('saving a commission rate feeds the product breakdown', function () {
    [$user, $workspace, $statement, $product] = ups_seed();

    $base = "/workspaces/{$workspace->slug}/finance/income-statements/{$statement->id}/users/{$user->id}";

    $this->actingAs($user)
        ->put("{$base}/commission-rate", ['product_id' => $product->id, 'rate' => 0.05])
        ->assertRedirect();

    $this->assertDatabaseHas('finance_commission_rates', [
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'product_id' => $product->id,
        'rate' => 0.05,
    ]);

    // WIDGET nets 927.60 gross; a gencys partner isn't set, so net = gross.
    // Commission = 5% of net profit.
    $this->actingAs($user)
        ->get($base)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('statement.products', function ($products) {
                $widget = collect($products)->firstWhere('product', 'WIDGET');

                return (float) $widget['commission_rate'] === 0.05
                    && (float) $widget['commission'] === round((float) $widget['net_profit'] * 0.05, 2);
            })
        );
});

test('shared OPEX is split across products by parcel share', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    Intern::create([
        'workspace_id' => $workspace->id,
        'intern_id' => 1,
        'full_name' => 'Juan Dela Cruz',
        'active' => true,
        'user_id' => $user->id,
    ]);

    $product = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);
    InventoryUnitCode::create([
        'workspace_id' => $workspace->id,
        'unit_code' => 'UC1',
        'product_id' => $product->id,
    ]);

    // This user: 2 delivered WIDGET parcels at 500 each.
    ups_order(820001, $workspace, 'Juan Dela Cruz', ['price_final' => 500], 'UC1');
    ups_order(820002, $workspace, 'Juan Dela Cruz', ['price_final' => 500], 'UC1');

    // Another intern: 8 more delivered parcels, so the company total is 10 and
    // WIDGET's parcel share is 2/10 = 20%.
    foreach (range(1, 8) as $i) {
        ups_order(820100 + $i, $workspace, 'Someone Else', ['price_final' => 100], null);
    }

    // A company-wide OPEX pool of 1,000.
    $account = Account::create([
        'workspace_id' => $workspace->id,
        'name' => 'Cash',
    ]);
    $salary = TransactionType::create([
        'workspace_id' => $workspace->id,
        'name' => 'SALARY',
        'income_statement_section' => 'opex',
    ]);
    Transaction::create([
        'workspace_id' => $workspace->id,
        'account_id' => $account->id,
        'date' => '2026-05-15',
        'description' => 'May payroll',
        'type' => 'out',
        'transaction_type_id' => $salary->id,
        'amount' => 1000,
    ]);

    $statement = IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'status' => 'final',
    ]);

    $widget = collect(app(UserIncomeStatementService::class)->userPayload($statement, $user)['products'])
        ->firstWhere('product', 'WIDGET');

    // 2 of the company's 10 delivered parcels.
    expect((float) $widget['parcel_share'])->toBe(0.2)
        // 20% of the 1,000 SALARY pool.
        ->and((float) $widget['opex'])->toBe(200.0)
        ->and($widget['opex_lines'])->toHaveCount(1)
        ->and($widget['opex_lines'][0]['type_name'])->toBe('SALARY')
        ->and((float) $widget['opex_lines'][0]['amount'])->toBe(200.0)
        // 1000 − (COD 20 + VAT 2.40) = 977.60 gross; net = gross − OPEX.
        ->and((float) $widget['gross_profit'])->toBe(977.60)
        ->and((float) $widget['net_profit'])->toBe(777.60);
});

test('each OPEX pool is split by the basis configured on its type', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    Intern::create([
        'workspace_id' => $workspace->id,
        'intern_id' => 1,
        'full_name' => 'Juan Dela Cruz',
        'active' => true,
        'user_id' => $user->id,
    ]);

    $product = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);
    InventoryUnitCode::create([
        'workspace_id' => $workspace->id,
        'unit_code' => 'UC1',
        'product_id' => $product->id,
    ]);

    // This user: 2 delivered WIDGET parcels, both ordered in the month.
    ups_order(830001, $workspace, 'Juan Dela Cruz', ['price_final' => 500, 'order_date' => '2026-05-02 09:00:00'], 'UC1');
    ups_order(830002, $workspace, 'Juan Dela Cruz', ['price_final' => 500, 'order_date' => '2026-05-03 09:00:00'], 'UC1');

    // Another intern: 8 more delivered → 10 delivered parcels company-wide.
    foreach (range(1, 8) as $i) {
        ups_order(830100 + $i, $workspace, 'Someone Else', ['price_final' => 100, 'order_date' => '2026-05-04 09:00:00'], null);
    }

    // 10 returned parcels — they were still orders someone took, so they count
    // toward total orders (20) but not delivered parcels (10).
    foreach (range(1, 10) as $i) {
        ups_order(830200 + $i, $workspace, 'Someone Else', [
            'parcel_status' => 'RETURNED',
            'price_final' => 100,
            'order_date' => '2026-05-05 09:00:00',
        ], null);
    }

    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);

    $pool = function (string $name, ?string $basis) use ($workspace, $account) {
        $type = TransactionType::create([
            'workspace_id' => $workspace->id,
            'name' => $name,
            'income_statement_section' => 'opex',
            'opex_allocation_basis' => $basis,
        ]);

        Transaction::create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'date' => '2026-05-15',
            'description' => $name,
            'type' => 'out',
            'transaction_type_id' => $type->id,
            'amount' => 1000,
        ]);
    };

    $pool('WAREHOUSE', 'delivered_parcels');
    $pool('CSR SALARY', 'total_orders');

    $statement = IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'status' => 'final',
    ]);

    $widget = collect(app(UserIncomeStatementService::class)->userPayload($statement, $user)['products'])
        ->firstWhere('product', 'WIDGET');

    $lines = collect($widget['opex_lines'])->keyBy('type_name');

    // 2 of 10 delivered parcels = 20% of the warehouse pool.
    expect((float) $lines['WAREHOUSE']['share'])->toBe(0.2)
        ->and((float) $lines['WAREHOUSE']['amount'])->toBe(200.0)
        // 2 of 20 orders placed = 10% of the CSR pool — the returns count here.
        ->and((float) $lines['CSR SALARY']['share'])->toBe(0.1)
        ->and((float) $lines['CSR SALARY']['amount'])->toBe(100.0)
        ->and($lines['CSR SALARY']['basis_label'])->toBe('Total orders')
        ->and((float) $widget['opex'])->toBe(300.0)
        // Parcel Share stays on delivered parcels regardless of the pools.
        ->and((float) $widget['parcel_share'])->toBe(0.2)
        // 977.60 gross − 300 OPEX
        ->and((float) $widget['net_profit'])->toBe(677.60);
});

test('Delivered is the product total across every intern, not just this user', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    Intern::create([
        'workspace_id' => $workspace->id,
        'intern_id' => 1,
        'full_name' => 'Juan Dela Cruz',
        'active' => true,
        'user_id' => $user->id,
    ]);

    $product = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);
    InventoryUnitCode::create([
        'workspace_id' => $workspace->id,
        'unit_code' => 'UC1',
        'product_id' => $product->id,
    ]);

    // Juan sells 3 WIDGETs at 100.
    foreach (range(1, 3) as $i) {
        ups_order(840000 + $i, $workspace, 'Juan Dela Cruz', ['price_final' => 100], 'UC1');
    }

    // Another intern sells 1 more of the same product — it counts toward the
    // product's Delivered but not toward Juan's slice of it.
    ups_order(840010, $workspace, 'Someone Else', ['price_final' => 100], 'UC1');

    $statement = IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'status' => 'final',
    ]);

    $widget = collect(app(UserIncomeStatementService::class)->userPayload($statement, $user)['products'])
        ->firstWhere('product', 'WIDGET');

    expect((float) $widget['product_delivered'])->toBe(400.0)
        ->and((int) $widget['product_orders'])->toBe(4)
        // Juan carries 3 of the product's 4 parcels.
        ->and((float) $widget['intern_share'])->toBe(0.75)
        // His own delivered revenue is still only his three orders.
        ->and((float) $widget['delivered'])->toBe(300.0)
        ->and((int) $widget['orders'])->toBe(3)
        // Sanity: the sheet's identity, Total Delivered = Delivered x share.
        ->and(round($widget['product_delivered'] * $widget['intern_share'], 2))->toBe(300.0);
});

test('OPEX tagged to a product is charged to it outright, the rest is shared', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    Intern::create([
        'workspace_id' => $workspace->id,
        'intern_id' => 1,
        'full_name' => 'Juan Dela Cruz',
        'active' => true,
        'user_id' => $user->id,
    ]);

    foreach ([['WIDGET', 'UC1'], ['GADGET', 'UC2']] as [$name, $code]) {
        $p = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => $name]);
        InventoryUnitCode::create(['workspace_id' => $workspace->id, 'unit_code' => $code, 'product_id' => $p->id]);
    }

    // 5 parcels each, so the two products split the company 50/50.
    foreach (range(1, 5) as $i) {
        ups_order(850000 + $i, $workspace, 'Juan Dela Cruz', ['price_final' => 100], 'UC1');
        ups_order(850100 + $i, $workspace, 'Juan Dela Cruz', ['price_final' => 100], 'UC2');
    }

    // A 1,000 sticker run, 400 of it tagged to WIDGET.
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    $type = TransactionType::create([
        'workspace_id' => $workspace->id,
        'name' => 'STICKER',
        'income_statement_section' => 'opex',
    ]);
    $txn = Transaction::create([
        'workspace_id' => $workspace->id,
        'account_id' => $account->id,
        'date' => '2026-05-15',
        'description' => 'Sticker run',
        'type' => 'out',
        'transaction_type_id' => $type->id,
        'amount' => 1000,
    ]);
    TransactionProduct::create([
        'transaction_id' => $txn->id,
        'product' => 'WIDGET',
        'amount' => 400,
    ]);

    $statement = IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'status' => 'final',
    ]);

    $rows = collect(app(UserIncomeStatementService::class)->userPayload($statement, $user)['products']);
    $line = fn (string $product) => collect($rows->firstWhere('product', $product)['opex_lines'])->firstWhere('type_name', 'STICKER');

    // WIDGET: its own 400, plus half of the 600 left to share.
    expect((float) $line('WIDGET')['direct'])->toBe(400.0)
        ->and((float) $line('WIDGET')['shared'])->toBe(300.0)
        ->and((float) $line('WIDGET')['amount'])->toBe(700.0)
        // GADGET carries none of the tag, just its half of the remainder.
        ->and((float) $line('GADGET')['direct'])->toBe(0.0)
        ->and((float) $line('GADGET')['shared'])->toBe(300.0)
        ->and((float) $line('GADGET')['amount'])->toBe(300.0);

    // Nothing is created or lost in the split.
    expect((float) $line('WIDGET')['amount'] + (float) $line('GADGET')['amount'])->toBe(1000.0);
});

test('a product that lost money last month carries the loss into this one', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    Intern::create([
        'workspace_id' => $workspace->id,
        'intern_id' => 1,
        'full_name' => 'Juan Dela Cruz',
        'active' => true,
        'user_id' => $user->id,
    ]);

    $product = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);
    InventoryUnitCode::create(['workspace_id' => $workspace->id, 'unit_code' => 'UC1', 'product_id' => $product->id]);

    // April: one 100 parcel against a 500 rent bill — a loss.
    ups_order(860001, $workspace, 'Juan Dela Cruz', [
        'price_final' => 100,
        'shipping_fee' => 0,
        'parcel_updated_date' => '2026-04-10 09:00:00',
        'shipped_out_date' => '2026-04-05',
    ], 'UC1');

    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    $rent = TransactionType::create([
        'workspace_id' => $workspace->id,
        'name' => 'RENT',
        'income_statement_section' => 'opex',
    ]);
    Transaction::create([
        'workspace_id' => $workspace->id,
        'account_id' => $account->id,
        'date' => '2026-04-15',
        'description' => 'April rent',
        'type' => 'out',
        'transaction_type_id' => $rent->id,
        'amount' => 500,
    ]);

    // May: two 500 parcels, no OPEX of its own.
    ups_order(860002, $workspace, 'Juan Dela Cruz', ['price_final' => 500, 'shipping_fee' => 0], 'UC1');
    ups_order(860003, $workspace, 'Juan Dela Cruz', ['price_final' => 500, 'shipping_fee' => 0], 'UC1');

    $statement = IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-05-01',
        'cod_fee_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'status' => 'final',
    ]);

    $widget = collect(app(UserIncomeStatementService::class)->userPayload($statement, $user)['products'])
        ->firstWhere('product', 'WIDGET');

    // April netted 100 − (COD 2 + VAT 0.24) − 500 rent = −402.24.
    expect((float) $widget['previous_loss'])->toBe(402.24)
        // May gross 1000 − (COD 20 + VAT 2.40) = 977.60, less April's loss.
        ->and((float) $widget['gross_profit'])->toBe(977.60)
        ->and((float) $widget['opex'])->toBe(0.0)
        ->and((float) $widget['net_profit'])->toBe(575.36);
});
