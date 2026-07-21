<?php

use Modules\Finance\Models\Account;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Models\TransactionType;
use Modules\GencysERP\Models\GencysDailySalesOrder;

function purl($workspace, string $path = ''): string
{
    return "/workspaces/{$workspace->slug}/finance/product-income-statements{$path}";
}

function pType($workspace, string $name, bool $gpd = false): TransactionType
{
    return TransactionType::create([
        'workspace_id' => $workspace->id,
        'name' => $name,
        'is_gross_profit_deduction' => $gpd,
    ]);
}

function pTxn($workspace, Account $account, ?TransactionType $type, string $inout, float $amount, string $date, ?string $product): Transaction
{
    return Transaction::create([
        'workspace_id' => $workspace->id,
        'account_id' => $account->id,
        'date' => $date,
        'description' => 'Test',
        'type' => $inout,
        'transaction_type_id' => $type?->id,
        'product' => $product,
        'amount' => $amount,
    ]);
}

function pOrder($workspace, array $attrs = []): GencysDailySalesOrder
{
    static $id = 500000;

    return GencysDailySalesOrder::create(array_merge([
        'id' => $id++,
        'workspace_id' => $workspace->id,
        'parcel_status' => 'DELIVERED',
        'price_final' => 0,
        'shipping_fee' => 0,
    ], $attrs));
}

/**
 * Seed May 2026 for product WIDGET: delivered 8,000 (2 orders), shipping 300,
 * a tagged Ad Spent (cost of sales) 1,000 + tagged expenses (OPEX) 500. Plus
 * noise for a different product. COD @2% = 160, VAT @12% = 19.20.
 */
function seedWidget($workspace): array
{
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    $adSpent = pType($workspace, 'Ad Spent', gpd: true);
    $expenses = pType($workspace, 'expenses');

    pOrder($workspace, ['order_details' => '1X WIDGET', 'parcel_updated_date' => '2026-05-10 09:00:00', 'price_final' => 5000, 'shipped_out_date' => '2026-05-05', 'shipping_fee' => 200]);
    pOrder($workspace, ['order_details' => '2X WIDGET', 'parcel_updated_date' => '2026-05-20 09:00:00', 'price_final' => 3000, 'shipped_out_date' => '2026-05-08', 'shipping_fee' => 100]);
    pOrder($workspace, ['order_details' => '1X GADGET', 'parcel_updated_date' => '2026-05-15 09:00:00', 'price_final' => 9999, 'shipped_out_date' => '2026-05-09', 'shipping_fee' => 999]); // other product

    pTxn($workspace, $account, $adSpent, 'out', 1000, '2026-05-14', 'WIDGET');   // cost of sales
    pTxn($workspace, $account, $expenses, 'out', 500, '2026-05-15', 'WIDGET');   // OPEX
    pTxn($workspace, $account, $expenses, 'out', 777, '2026-05-16', 'GADGET');   // other product

    return compact('account', 'adSpent', 'expenses');
}

test('preview scopes revenue + tagged expenses to the normalized product', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    seedWidget($workspace);

    $this->actingAs($user)
        ->get(purl($workspace, '/preview?month=2026-05&product=WIDGET'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/finance/income-statements/show')
            ->where('scope.label', 'WIDGET')
            ->where('statement.delivered', fn ($v) => (float) $v === 8000.0)
            ->where('statement.orders', 2)
            ->where('statement.expenses.0.source', 'shipping_fee')
            ->where('statement.expenses.0.amount', fn ($v) => (float) $v === 300.0)
            // Ad Spent (flagged, tagged WIDGET) appears in cost of sales; GADGET's don't
            ->where('statement.expenses', fn ($rows) => collect($rows)->contains(fn ($r) => $r['type_name'] === 'Ad Spent' && $r['section'] === 'cost_of_sales' && (float) $r['amount'] === 1000.0)
                && collect($rows)->contains(fn ($r) => $r['type_name'] === 'expenses' && $r['section'] === 'opex' && (float) $r['amount'] === 500.0))
        );
});

test('store computes the per-product two-tier P&L', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['adSpent' => $adSpent, 'expenses' => $expenses] = seedWidget($workspace);

    $this->actingAs($user)
        ->post(purl($workspace), [
            'month' => '2026-05',
            'product' => 'WIDGET',
            'cod_rate' => 0.02,
            'vat_rate' => 0.12,
            'included_keys' => [-1, -2, -3, $adSpent->id, $expenses->id],
        ])
        ->assertRedirect();

    // Cost of sales = 300 + 160 + 19.20 + 1000 = 1479.20 → Gross = 8000 − 1479.20 = 6520.80
    // OPEX = 500 → Net = 6020.80 (advisory 0: workspace not a gencys partner)
    $this->assertDatabaseHas('finance_product_income_statements', [
        'workspace_id' => $workspace->id,
        'product' => 'WIDGET',
        'total_delivered' => 8000,
        'gross_profit' => 6520.80,
        'total_expenses' => 1979.20,
        'net_profit' => 6020.80,
    ]);

    $this->assertDatabaseHas('finance_product_income_statement_expenses', ['type_name' => 'Ad Spent', 'section' => 'cost_of_sales', 'amount' => 1000]);
    $this->assertDatabaseHas('finance_product_income_statement_expenses', ['type_name' => 'expenses', 'section' => 'opex', 'amount' => 500]);
});

test('a member without finance permission is forbidden', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = makeWorkspaceMember($workspace, 'member');

    $this->actingAs($member)
        ->get(purl($workspace))
        ->assertForbidden();
});
