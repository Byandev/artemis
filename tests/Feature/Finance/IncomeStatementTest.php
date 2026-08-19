<?php

use Modules\Finance\Models\Account;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Models\TransactionType;
use Modules\GencysERP\Models\GencysDailySalesOrder;

function isUrl($workspace, string $path = ''): string
{
    return "/workspaces/{$workspace->slug}/finance/income-statements{$path}";
}

function makeType($workspace, string $name, ?string $section = 'opex'): TransactionType
{
    return TransactionType::create([
        'workspace_id' => $workspace->id,
        'name' => $name,
        'income_statement_section' => $section,
    ]);
}

function makeTxn($workspace, Account $account, ?TransactionType $type, string $type_in_out, float $amount, string $date): Transaction
{
    return Transaction::create([
        'workspace_id' => $workspace->id,
        'account_id' => $account->id,
        'date' => $date,
        'description' => 'Test',
        'type' => $type_in_out,
        'transaction_type_id' => $type?->id,
        'amount' => $amount,
    ]);
}

function makeGencysOrder($workspace, array $attrs = []): GencysDailySalesOrder
{
    static $id = 100000;

    return GencysDailySalesOrder::create(array_merge([
        'id' => $id++,
        'workspace_id' => $workspace->id,
        'parcel_status' => 'DELIVERED',
        'price_final' => 0,
        'shipping_fee' => 0,
    ], $attrs));
}

/**
 * Seed May 2026: delivered 10,000 (2 orders), shipping 700. Transaction types:
 * "Ad Spent" (flagged → cost of sales) 3,000; "expenses" + "transfer" (unflagged
 * → OPEX) 1,000 + 500. COD @2% = 200, VAT @12% = 24.
 */
function seedMay($workspace): array
{
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    $adSpent = makeType($workspace, 'Ad Spent', section: 'cost_of_sales');
    $expenses = makeType($workspace, 'expenses');
    $transfer = makeType($workspace, 'transfer');

    makeGencysOrder($workspace, ['parcel_status' => 'DELIVERED', 'parcel_updated_date' => '2026-05-10 09:00:00', 'price_final' => 6000, 'shipped_out_date' => '2026-05-05', 'shipping_fee' => 150]);
    makeGencysOrder($workspace, ['parcel_status' => 'DELIVERED', 'parcel_updated_date' => '2026-05-20 09:00:00', 'price_final' => 4000, 'shipped_out_date' => '2026-05-08', 'shipping_fee' => 250]);
    makeGencysOrder($workspace, ['parcel_status' => 'DELIVERED', 'parcel_updated_date' => '2026-04-25 09:00:00', 'price_final' => 3000, 'shipped_out_date' => '2026-04-20', 'shipping_fee' => 100]);
    makeGencysOrder($workspace, ['parcel_status' => 'IN-TRANSIT', 'parcel_updated_date' => '2026-05-15 09:00:00', 'price_final' => 7000, 'shipped_out_date' => '2026-05-12', 'shipping_fee' => 300]);

    makeTxn($workspace, $account, $adSpent, 'out', 3000, '2026-05-14');   // flagged → cost of sales
    makeTxn($workspace, $account, $expenses, 'out', 1000, '2026-05-15');  // OPEX
    makeTxn($workspace, $account, $transfer, 'out', 500, '2026-05-16');   // OPEX
    makeTxn($workspace, $account, $expenses, 'out', 200, '2026-04-15');   // outside month
    makeTxn($workspace, $account, $expenses, 'in', 999, '2026-05-17');    // inflow

    return compact('account', 'adSpent', 'expenses', 'transfer');
}

test('preview splits cost of sales (auto + flagged types) from OPEX (unflagged)', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    seedMay($workspace);

    $this->actingAs($user)
        ->get(isUrl($workspace, '/preview?month=2026-05'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/finance/income-statements/show')
            ->where('statement.delivered', fn ($v) => (float) $v === 10000.0)
            // auto cost-of-sales first: shipping, cod, vat
            ->where('statement.expenses.0.source', 'shipping_fee')
            ->where('statement.expenses.0.section', 'cost_of_sales')
            ->where('statement.expenses.1.source', 'cod_fee')
            ->where('statement.expenses.2.source', 'vat')
            // then the flagged transaction type (Ad Spent) in cost of sales
            ->where('statement.expenses.3.type_name', 'Ad Spent')
            ->where('statement.expenses.3.section', 'cost_of_sales')
            ->where('statement.expenses.3.amount', fn ($v) => (float) $v === 3000.0)
            // then OPEX (unflagged) buckets
            ->where('statement.expenses.4.section', 'opex')
            ->has('statement.expenses', 6)
        );
});

test('store computes gross profit from cost of sales and net profit from OPEX', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['adSpent' => $adSpent, 'expenses' => $expenses] = seedMay($workspace);

    $this->actingAs($user)
        ->post(isUrl($workspace), [
            'month' => '2026-05',
            'cod_rate' => 0.02,
            'vat_rate' => 0.12,
            // shipping(-1), cod(-2), vat(-3), Ad Spent (its type id), expenses — exclude transfer
            'included_keys' => [-1, -2, -3, $adSpent->id, $expenses->id],
        ])
        ->assertRedirect();

    // Cost of sales = 700 + 200 + 24 + 3000 = 3924 → Gross = 10,000 − 3924 = 6076
    // OPEX = 1000 → Net = 6076 − 1000 = 5076
    $this->assertDatabaseHas('finance_income_statements', [
        'workspace_id' => $workspace->id,
        'total_delivered' => 10000,
        'gross_profit' => 6076,
        'total_expenses' => 4924,
        'net_profit' => 5076,
    ]);

    $this->assertDatabaseCount('finance_income_statement_expenses', 5);
    $this->assertDatabaseHas('finance_income_statement_expenses', ['type_name' => 'Ad Spent', 'section' => 'cost_of_sales', 'amount' => 3000]);
    $this->assertDatabaseHas('finance_income_statement_expenses', ['source' => 'shipping_fee', 'section' => 'cost_of_sales', 'amount' => 700]);
    $this->assertDatabaseHas('finance_income_statement_expenses', ['type_name' => 'expenses', 'section' => 'opex', 'amount' => 1000]);
    $this->assertDatabaseMissing('finance_income_statement_expenses', ['type_name' => 'transfer']);
});

test('a type flagged as gross profit deduction moves from OPEX to cost of sales', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['expenses' => $expenses] = seedMay($workspace);

    // Initially "expenses" is OPEX.
    $this->actingAs($user)
        ->get(isUrl($workspace, '/preview?month=2026-05'))
        ->assertInertia(fn ($page) => $page->where(
            'statement.expenses',
            fn ($rows) => collect($rows)->firstWhere('type_name', 'expenses')['section'] === 'opex',
        ));

    $expenses->update(['income_statement_section' => 'cost_of_sales']);

    // Now it's a cost-of-sales line.
    $this->actingAs($user)
        ->get(isUrl($workspace, '/preview?month=2026-05'))
        ->assertInertia(fn ($page) => $page->where(
            'statement.expenses',
            fn ($rows) => collect($rows)->firstWhere('type_name', 'expenses')['section'] === 'cost_of_sales',
        ));
});

test('advisory share deducts a % of gross profit for gencys partners', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['is_gencys_partner' => true]);
    ['adSpent' => $adSpent, 'expenses' => $expenses, 'transfer' => $transfer] = seedMay($workspace);

    $this->actingAs($user)->post(isUrl($workspace), [
        'month' => '2026-05',
        'cod_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'included_keys' => [-1, -2, -3, $adSpent->id, $expenses->id, $transfer->id],
    ])->assertRedirect();

    // Cost of sales = 700 + 200 + 24 + 3000 = 3924 → Gross = 6076
    // Advisory = 30% × 6076 = 1822.80 ; OPEX = 1500 ; Net = 6076 − 1500 − 1822.80 = 2753.20
    $this->assertDatabaseHas('finance_income_statements', [
        'workspace_id' => $workspace->id,
        'gross_profit' => 6076,
        'advisory_rate' => 0.30,
        'advisory_share' => 1822.80,
        'net_profit' => 2753.20,
    ]);
});

test('advisory share is not applied to non-gencys-partner workspaces', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['adSpent' => $adSpent] = seedMay($workspace); // is_gencys_partner defaults false

    $this->actingAs($user)->post(isUrl($workspace), [
        'month' => '2026-05',
        'included_keys' => [-1, -2, -3, $adSpent->id],
    ])->assertRedirect();

    $this->assertDatabaseHas('finance_income_statements', [
        'workspace_id' => $workspace->id,
        'advisory_share' => 0,
    ]);
});

test('regenerate re-pulls with the snapshotted rate and included lines', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    seedMay($workspace);

    $this->actingAs($user)->post(isUrl($workspace), [
        'month' => '2026-05',
        'cod_rate' => 0.02,
        'vat_rate' => 0.12,
        'included_keys' => [-2], // COD only → 200
    ]);

    $statement = IncomeStatement::first();
    expect((float) $statement->gross_profit)->toBe(9800.0);

    makeGencysOrder($workspace, ['parcel_status' => 'DELIVERED', 'parcel_updated_date' => '2026-05-28 09:00:00', 'price_final' => 5000, 'shipped_out_date' => '2026-05-27', 'shipping_fee' => 0]);

    $this->actingAs($user)
        ->post(isUrl($workspace, "/{$statement->id}/regenerate"))
        ->assertRedirect();

    $statement->refresh();
    expect((float) $statement->total_delivered)->toBe(15000.0);
    expect((float) $statement->gross_profit)->toBe(14700.0);
    $this->assertDatabaseCount('finance_income_statement_expenses', 1);
});

test('transaction type stores the nature and income statement section', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($user)
        ->post("/workspaces/{$workspace->slug}/finance/transaction-types", [
            'name' => 'COG',
            'nature' => 'debit',
            'income_statement_section' => 'cost_of_sales',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('finance_transaction_types', [
        'workspace_id' => $workspace->id,
        'name' => 'COG',
        'nature' => 'debit',
        'income_statement_section' => 'cost_of_sales',
    ]);
});

test('a transaction type can be excluded from the income statement', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($user)
        ->post("/workspaces/{$workspace->slug}/finance/transaction-types", [
            'name' => 'Transfer of Funds',
            'nature' => 'debit',
            'income_statement_section' => null,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('finance_transaction_types', [
        'workspace_id' => $workspace->id,
        'name' => 'Transfer of Funds',
        'income_statement_section' => null,
    ]);
});

test('a member without finance permission is forbidden', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = makeWorkspaceMember($workspace, 'member');

    $this->actingAs($member)
        ->get(isUrl($workspace))
        ->assertForbidden();
});
