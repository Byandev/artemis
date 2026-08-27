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

test('saving splits cost of sales (auto + flagged types) from OPEX (unflagged)', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    seedMay($workspace);

    // Omitting included_keys takes every line.
    $this->actingAs($user)
        ->post(isUrl($workspace), ['month' => '2026-05', 'cod_rate' => 0.02, 'vat_rate' => 0.12])
        ->assertRedirect();

    $rows = IncomeStatement::first()->breakdown;

    // The auto lines: shipping, COD and the VAT on it.
    expect($rows->where('source', 'shipping_fee')->first()->section)->toBe('cost_of_sales')
        ->and($rows->where('source', 'cod_fee')->first()->section)->toBe('cost_of_sales')
        ->and($rows->where('source', 'vat')->first()->section)->toBe('cost_of_sales')
        // A type tagged cost of sales sits with them...
        ->and($rows->firstWhere('type_name', 'Ad Spent')->section)->toBe('cost_of_sales')
        ->and((float) $rows->firstWhere('type_name', 'Ad Spent')->amount)->toBe(3000.0)
        // ...and an untagged one falls to OPEX.
        ->and($rows->firstWhere('type_name', 'expenses')->section)->toBe('opex')
        ->and($rows)->toHaveCount(6);
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

test('a type tagged as cost of sales moves off OPEX', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['expenses' => $expenses] = seedMay($workspace);

    $save = fn () => $this->actingAs($user)
        ->post(isUrl($workspace), ['month' => '2026-05', 'cod_rate' => 0.02, 'vat_rate' => 0.12])
        ->assertRedirect();

    $sectionOfExpenses = fn () => IncomeStatement::first()->breakdown()
        ->where('type_name', 'expenses')->first()->section;

    $save();
    expect($sectionOfExpenses())->toBe('opex');

    $expenses->update(['income_statement_section' => 'cost_of_sales']);

    $save();
    expect($sectionOfExpenses())->toBe('cost_of_sales');
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

    // Regenerate keeps the saved OPEX choices but always re-includes every
    // cost-of-sales line, so a type newly tagged as cost of sales flows into
    // gross profit instead of being dropped for not being in the original set.
    // Shipping 700 + Ad Spent 3,000 + COD 300 + VAT 36 = 4,036.
    expect((float) $statement->gross_profit)->toBe(10964.0);
    $this->assertDatabaseCount('finance_income_statement_expenses', 4);
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

test('an OPEX type stores the basis its pool is split across products by', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $url = "/workspaces/{$workspace->slug}/finance/transaction-types";

    $this->actingAs($user)
        ->post($url, [
            'name' => 'CSR Salary',
            'nature' => 'debit',
            'income_statement_section' => 'opex',
            'opex_allocation_basis' => 'total_orders',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('finance_transaction_types', [
        'workspace_id' => $workspace->id,
        'name' => 'CSR Salary',
        'income_statement_section' => 'opex',
        'opex_allocation_basis' => 'total_orders',
    ]);

    // An unknown basis is rejected rather than silently stored.
    $this->actingAs($user)
        ->post($url, [
            'name' => 'Nonsense',
            'nature' => 'debit',
            'income_statement_section' => 'opex',
            'opex_allocation_basis' => 'phase_of_the_moon',
        ])
        ->assertSessionHasErrors('opex_allocation_basis');

    // Left unset, the type falls back to the default basis.
    $this->actingAs($user)
        ->post($url, ['name' => 'Rent', 'nature' => 'debit', 'income_statement_section' => 'opex'])
        ->assertRedirect();

    $rent = TransactionType::where('name', 'Rent')->first();
    expect($rent->opex_allocation_basis)->toBeNull()
        ->and($rent->allocationBasis())->toBe('delivered_parcels');
});

test('the transaction types page offers the allocation bases', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs($user)
        ->get("/workspaces/{$workspace->slug}/finance/transaction-types")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('defaultAllocationBasis', 'delivered_parcels')
            ->where('allocationBases', fn ($bases) => collect($bases)->pluck('value')->all() === [
                'delivered_parcels', 'total_orders', 'delivered_revenue',
            ])
        );
});

test('the statement carries the same figures as its per-product and per-user slices', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    seedMay($workspace);

    // An order with no platform or page recorded still belongs on the statement.
    makeGencysOrder($workspace, [
        'parcel_status' => 'DELIVERED',
        'parcel_updated_date' => '2026-05-14 09:00:00',
        'price_final' => 1000,
        'total_cog' => 250,
        'shipped_out_date' => '2026-05-11',
        'shipping_fee' => 90,
    ]);

    $this->actingAs($user)->post(isUrl($workspace), [
        'month' => '2026-05',
        'cod_rate' => 0.02,
        'vat_rate' => 0.12,
        'included_keys' => [-2],
    ])->assertRedirect();

    $statement = IncomeStatement::first();

    // 10,000 from the seed plus the 1,000 order with no platform or page.
    expect((float) $statement->total_delivered)->toBe(11000.0)
        ->and((int) $statement->delivered_orders)->toBe(3)
        // Shipping counts the same order set the revenue does: 150 + 250 + 90.
        ->and((int) $statement->shipped_orders)->toBe(4)
        ->and((float) $statement->total_shipping_fee)->toBe(790.0)
        // The whole month's Ad Spent, tagged or not.
        ->and((float) $statement->ad_spent)->toBe(3000.0)
        // 2% of 11,000, then 12% of that.
        ->and((float) $statement->cod_fee)->toBe(220.0)
        ->and((float) $statement->cod_fee_vat)->toBe(26.4)
        ->and((float) $statement->total_delivered_cogs)->toBe(250.0);

    // Common costs = 3,000 + 790 + 220 + 26.40 = 4,036.40.
    // Delivered basis: 11,000 − 4,036.40 − 250 = 6,713.60
    expect((float) $statement->gross_profit_delivered_cogs)->toBe(6713.60)
        // Nothing was bought this month, so the bought basis charges no goods.
        ->and((float) $statement->gross_profit_bought_cogs)->toBe(6963.60);

    $this->actingAs($user)
        ->get(isUrl($workspace, "/{$statement->id}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('figures.delivered_amount', fn ($v) => (float) $v === 11000.0)
            ->where('figures.gross_profit_delivered_cogs', fn ($v) => (float) $v === 6713.60)
        );
});
