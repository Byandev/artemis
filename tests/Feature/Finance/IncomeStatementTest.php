<?php

use App\Models\Page;
use App\Models\PageDailyRecord;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Models\IncomeStatementSetting;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Models\TransactionProduct;
use Modules\Finance\Models\TransactionType;
use Modules\GencysERP\Models\GencysDailySalesOrder;
use Modules\Pancake\Models\Order as PancakeOrder;

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
    // The flag selects the order source, so gencys fixtures need it set.
    $workspace->update(['is_gencys_partner' => true]);

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

/**
 * May 2026 on the pancake side: delivered 10,000 across two orders, 700 of
 * shipping on three shipped parcels. No cost of goods — pancake records none.
 */
function seedMayPancake($workspace, ?int $ownerId = null): void
{
    $shop = Shop::create(['workspace_id' => $workspace->id, 'name' => 'Shop']);
    $page = Page::create([
        'workspace_id' => $workspace->id,
        'shop_id' => $shop->id,
        'name' => 'Page',
        // Who the orders on this page are credited to.
        'owner_id' => $ownerId ?? $workspace->users()->value('users.id'),
    ]);

    $order = fn (array $attrs) => PancakeOrder::create(array_merge([
        'workspace_id' => $workspace->id,
        'order_number' => fake()->unique()->numerify('PC-#####'),
        'status' => 3,
        'status_name' => 'delivered',
        'shop_id' => $shop->id,
        'page_id' => $page->id,
        'customer_id' => (string) Str::uuid(),
        'inserted_at' => '2026-05-01 09:00:00',
    ], $attrs));

    $order(['final_amount' => 6000, 'delivered_at' => '2026-05-10 09:00:00', 'shipped_at' => '2026-05-05 09:00:00', 'shipping_fee' => 300]);
    $order(['final_amount' => 4000, 'delivered_at' => '2026-05-20 09:00:00', 'shipped_at' => '2026-05-08 09:00:00', 'shipping_fee' => 250]);
    // Shipped inside the month but not delivered — shipping counts, revenue doesn't.
    $order(['final_amount' => 7000, 'shipped_at' => '2026-05-12 09:00:00', 'shipping_fee' => 150]);
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
    // Gross 6,076 and OPEX 1,000 would leave 5,076, but seedMay is a gencys
    // workspace so the advisory share comes off as well: 30% of 6,076 is
    // 1,822.80, leaving 3,253.20.
    $this->assertDatabaseHas('finance_income_statements', [
        'workspace_id' => $workspace->id,
        'total_delivered' => 10000,
        'gross_profit' => 6076,
        'total_expenses' => 4924,
        // opex is the month's OPEX-marked transactions on their own: the 1,000
        // "expenses" and the 500 "transfer".
        'opex' => 1500,
        'net_profit' => 3253.20,
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

test('a non-partner workspace reads pancake orders instead of gencys', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    seedMayPancake($workspace); // is_gencys_partner defaults false

    $this->actingAs($user)->post(isUrl($workspace), [
        'month' => '2026-05', 'cod_rate' => 0.02, 'vat_rate' => 0.12,
    ])->assertRedirect();

    $statement = IncomeStatement::first();

    // Delivered by delivered_at, revenue from final_amount.
    expect((int) $statement->delivered_orders)->toBe(2)
        ->and((float) $statement->total_delivered)->toBe(10000.0)
        // Shipped by shipped_at, counting the undelivered parcel too.
        ->and((int) $statement->shipped_orders)->toBe(3)
        ->and((float) $statement->total_shipping_fee)->toBe(700.0)
        // Pancake records no cost of goods against an order.
        ->and((float) $statement->total_delivered_cogs)->toBe(0.0)
        // 10,000 − shipping 700 − COD 200 − VAT 24 = 9,076.
        ->and((float) $statement->gross_profit_delivered_cogs)->toBe(9076.0)
        // Not a partner, so no advisory on either basis.
        ->and((float) $statement->gross_profit_delivered_cogs_advisory_share)->toBe(0.0)
        ->and((float) $statement->advisory_share_on_delivered)->toBe(0.0)
        ->and((float) $statement->gross_profit_delivered_cogs_after_advisory_share)->toBe(9076.0);
});

test('advisory share is not applied to non-gencys-partner workspaces', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    seedMayPancake($workspace); // is_gencys_partner defaults false

    $this->actingAs($user)->post(isUrl($workspace), [
        'month' => '2026-05', 'cod_rate' => 0.02, 'vat_rate' => 0.12,
    ])->assertRedirect();

    $statement = IncomeStatement::first();

    // A real positive margin, and still nothing owed on either basis.
    expect((float) $statement->gross_profit_delivered_cogs)->toBeGreaterThan(0)
        ->and((float) $statement->gross_profit_delivered_cogs_advisory_share)->toBe(0.0)
        ->and((float) $statement->advisory_share_on_delivered)->toBe(0.0);
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

    // Every figure the card renders has to be in the payload — a missing one
    // reaches the page as undefined and renders as NaN.
    $this->actingAs($user)
        ->get(isUrl($workspace, "/{$statement->id}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('figures.delivered_amount', fn ($v) => (float) $v === 11000.0)
            ->where('figures.gross_profit_delivered_cogs', fn ($v) => (float) $v === 6713.60)
            ->has('figures.opex')
            ->has('figures.gross_profit_delivered_cogs_advisory_share')
            ->has('figures.gross_profit_bought_cogs_advisory_share')
            ->has('statement.advisory_rate')
            ->has('statement.gencys_partner')
        );
});

test('the advisory share is a cut of positive gross profit, partners only', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['is_gencys_partner' => true]);
    seedMay($workspace);

    $save = fn () => $this->actingAs($user)->post(isUrl($workspace), [
        'month' => '2026-05',
        'cod_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
    ])->assertRedirect();

    $save();
    $statement = IncomeStatement::first();

    // Delivered 10,000 less ad spend 3,000, shipping 700, COD 200 and VAT 24,
    // then the goods: nothing shipped with a cost on it, so gross is 6,076.
    // 30% of that is 1,822.80, but 9% of the 10,000 delivered is only 900 —
    // and the lower of the two is what gets charged.
    expect((float) $statement->gross_profit_delivered_cogs)->toBe(6076.0)
        ->and((float) $statement->gross_profit_delivered_cogs_advisory_share)->toBe(900.0)
        ->and((float) $statement->gross_profit_delivered_cogs_after_advisory_share)->toBe(5176.0)
        // Nothing was bought this month, so that basis is the same here.
        ->and((float) $statement->gross_profit_bought_cogs)->toBe(6076.0)
        ->and((float) $statement->gross_profit_bought_cogs_advisory_share)->toBe(900.0)
        ->and((float) $statement->gross_profit_bought_cogs_after_advisory_share)->toBe(5176.0);

    // A workspace that isn't a partner owes nothing on the same numbers.
    $workspace->update(['is_gencys_partner' => false]);
    $save();

    expect((float) $statement->fresh()->gross_profit_delivered_cogs_advisory_share)->toBe(0.0)
        // With nothing taken, the after figure is just the gross.
        ->and((float) $statement->fresh()->gross_profit_delivered_cogs_after_advisory_share)
        ->toBe((float) $statement->fresh()->gross_profit_delivered_cogs);
});

test('a loss owes no advisory share', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['is_gencys_partner' => true]);
    ['account' => $account, 'adSpent' => $adSpent] = seedMay($workspace);

    // Enough extra ad spend to push the month into a loss.
    makeTxn($workspace, $account, $adSpent, 'out', 20000, '2026-05-18');

    $this->actingAs($user)->post(isUrl($workspace), [
        'month' => '2026-05',
        'cod_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
    ])->assertRedirect();

    $statement = IncomeStatement::first();

    expect((float) $statement->gross_profit_delivered_cogs)->toBeLessThan(0)
        // A negative gross earns no rebate — it is simply nothing.
        ->and((float) $statement->gross_profit_delivered_cogs_advisory_share)->toBe(0.0)
        ->and((float) $statement->gross_profit_bought_cogs_advisory_share)->toBe(0.0);
});

test('the advisory share is worked out on both bases so the lower one shows', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['is_gencys_partner' => true]);
    seedMay($workspace);

    $this->actingAs($user)->post(isUrl($workspace), [
        'month' => '2026-05',
        'cod_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'advisory_delivered_rate' => 0.09,
    ])->assertRedirect();

    $statement = IncomeStatement::first();

    // Two ways to strike it: 30% of the 6,076 gross is 1,822.80, while 9% of
    // the 10,000 delivered is 900. The cheaper one is what is charged, and the
    // other is kept alongside so the statement can say which basis won.
    expect((float) $statement->advisory_share_on_delivered)->toBe(900.0)
        ->and((float) $statement->gross_profit_delivered_cogs_advisory_share)->toBe(900.0)
        // Both rates are snapshotted, so the comparison holds on a reread.
        ->and((float) $statement->advisory_rate)->toBe(0.30)
        ->and((float) $statement->advisory_delivered_rate)->toBe(0.09);

    $this->actingAs($user)
        ->get(isUrl($workspace, "/{$statement->id}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('figures.advisory_share_on_delivered', fn ($v) => (float) $v === 900.0)
            ->has('figures.gross_profit_delivered_cogs_after_advisory_share')
            ->has('statement.advisory_delivered_rate')
        );
});

test('the gross profit basis wins when the margin is thin', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['is_gencys_partner' => true]);
    ['account' => $account, 'adSpent' => $adSpent] = seedMay($workspace);

    // Push costs up so the margin is slim: 30% of a small gross now comes in
    // under 9% of the unchanged delivered revenue.
    makeTxn($workspace, $account, $adSpent, 'out', 5000, '2026-05-18');

    $this->actingAs($user)->post(isUrl($workspace), [
        'month' => '2026-05',
        'cod_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'advisory_delivered_rate' => 0.09,
    ])->assertRedirect();

    $statement = IncomeStatement::first();

    // Gross is 1,076, so 30% of it is 322.80 against 900 on the delivered basis.
    expect((float) $statement->gross_profit_delivered_cogs)->toBe(1076.0)
        ->and((float) $statement->advisory_share_on_delivered)->toBe(900.0)
        ->and((float) $statement->gross_profit_delivered_cogs_advisory_share)->toBe(322.80)
        ->and((float) $statement->gross_profit_delivered_cogs_after_advisory_share)->toBe(753.20);
});

test('neither advisory basis applies to a workspace that is not a partner', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    seedMayPancake($workspace); // is_gencys_partner defaults false

    $this->actingAs($user)->post(isUrl($workspace), [
        'month' => '2026-05',
        'cod_rate' => 0.02,
        'vat_rate' => 0.12,
    ])->assertRedirect();

    $statement = IncomeStatement::first();

    expect((float) $statement->advisory_share_on_delivered)->toBe(0.0)
        ->and((float) $statement->gross_profit_delivered_cogs_advisory_share)->toBe(0.0);
});

test('a non-partner takes its ad spend from the page daily records', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    seedMayPancake($workspace); // is_gencys_partner defaults false

    $page = Page::where('workspace_id', $workspace->id)->first();

    $record = fn (array $attrs) => DB::table('page_daily_records')->insert(array_merge([
        'workspace_id' => $workspace->id,
        'source' => PageDailyRecord::SOURCE_ARTEMIS,
        'page_type' => (new Page)->getMorphClass(),
        'page_id' => $page->id,
        'created_at' => now(),
        'updated_at' => now(),
    ], $attrs));

    $record(['date' => '2026-05-04', 'ad_spent' => 1200]);
    $record(['date' => '2026-05-19', 'ad_spent' => 800]);
    // Outside the month.
    $record(['date' => '2026-04-28', 'ad_spent' => 5000]);
    // The table is unique on workspace, page and date whatever wrote the row,
    // so a row from the other importer is just another day's spend, not a
    // duplicate to be filtered out.
    $record(['date' => '2026-05-21', 'ad_spent' => 500, 'source' => PageDailyRecord::SOURCE_GENCYS]);

    // An Ad Spent transaction must not reach a pancake workspace's statement.
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    $type = TransactionType::create([
        'workspace_id' => $workspace->id,
        'name' => 'Ad Spent',
        'income_statement_section' => 'cost_of_sales',
    ]);
    Transaction::create([
        'workspace_id' => $workspace->id,
        'account_id' => $account->id,
        'date' => '2026-05-15',
        'description' => 'Ledger ad spend',
        'type' => 'out',
        'transaction_type_id' => $type->id,
        'amount' => 7777,
    ]);

    $this->actingAs($user)->post(isUrl($workspace), [
        'month' => '2026-05', 'cod_rate' => 0.02, 'vat_rate' => 0.12,
    ])->assertRedirect();

    $statement = IncomeStatement::first();

    // 1,200 + 800 + 500 from the page records; not April's 5,000, and not the
    // 7,777 sitting in the ledger.
    expect((float) $statement->ad_spent)->toBe(2500.0)
        // 10,000 − ad 2,500 − shipping 700 − COD 200 − VAT 24 = 6,576.
        ->and((float) $statement->gross_profit_delivered_cogs)->toBe(6576.0);
});

test('a gencys partner still takes its ad spend from the ledger', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    seedMay($workspace); // marks the workspace as a gencys partner

    $this->actingAs($user)->post(isUrl($workspace), [
        'month' => '2026-05', 'cod_rate' => 0.02, 'vat_rate' => 0.12,
    ])->assertRedirect();

    // The 3,000 Ad Spent transaction seedMay creates, untouched by the change.
    expect((float) IncomeStatement::first()->ad_spent)->toBe(3000.0);
});

test('the COD fee rate follows the courier the workspace ships with', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    seedMayPancake($workspace); // is_gencys_partner defaults false

    // No saved rate, so the source's own is used.
    $this->actingAs($user)->post(isUrl($workspace), ['month' => '2026-05'])->assertRedirect();

    $statement = IncomeStatement::first();

    expect((float) $statement->cod_fee_rate)->toBe(0.0275)
        // 2.75% of the 10,000 delivered, then 12% of that fee.
        ->and((float) $statement->cod_fee)->toBe(275.0)
        ->and((float) $statement->cod_fee_vat)->toBe(33.0);

    // Saving writes the rate back as the workspace default.
    expect((float) IncomeStatementSetting::where('workspace_id', $workspace->id)->value('cod_fee_rate'))
        ->toBe(0.0275);
});

test('a gencys partner keeps the 2% COD rate', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    seedMay($workspace); // marks the workspace as a gencys partner

    $this->actingAs($user)->post(isUrl($workspace), ['month' => '2026-05'])->assertRedirect();

    expect((float) IncomeStatement::first()->cod_fee_rate)->toBe(0.02);
});

test('a rate the workspace saved for itself wins over the courier default', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    seedMayPancake($workspace);

    // Someone negotiated their own rate.
    IncomeStatementSetting::create([
        'workspace_id' => $workspace->id,
        'cod_fee_rate' => 0.015,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
    ]);

    $this->actingAs($user)->post(isUrl($workspace), ['month' => '2026-05'])->assertRedirect();

    expect((float) IncomeStatement::first()->cod_fee_rate)->toBe(0.015);
});

test('freight on a goods purchase is picked up and not counted as goods', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    seedMay($workspace);

    $account = Account::where('workspace_id', $workspace->id)->first();

    // The wording varies in practice; each of these is freight, not goods.
    foreach (['Delivery Fee of COGS', 'Delivery of COG', 'COG Delivery'] as $i => $name) {
        $type = TransactionType::create([
            'workspace_id' => $workspace->id,
            'name' => $name,
            'income_statement_section' => 'cost_of_sales',
        ]);

        makeTxn($workspace, $account, $type, 'out', 100 * ($i + 1), '2026-05-14');
    }

    $this->actingAs($user)->post(isUrl($workspace), [
        'month' => '2026-05', 'cod_rate' => 0.02, 'vat_rate' => 0.12,
    ])->assertRedirect();

    $statement = IncomeStatement::first();

    // 100 + 200 + 300, all three recognised as freight...
    expect((float) $statement->total_bought_cogs_delivery_fee)->toBe(600.0)
        // ...and none of them leaking into the goods line, which seedMay leaves
        // empty since it books no Cost of Goods transaction.
        ->and((float) $statement->total_bought_cogs)->toBe(0.0);
});

test('a goods purchase reaches the workspace, product and user statements alike', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    seedMay($workspace);

    $account = Account::where('workspace_id', $workspace->id)->first();
    $type = TransactionType::create([
        'workspace_id' => $workspace->id,
        'name' => 'Cost of Goods',
        'income_statement_section' => 'cost_of_sales',
    ]);

    $txn = makeTxn($workspace, $account, $type, 'out', 5000, '2026-05-14');
    // Tagged to a product and charged to a person, so all three grains see it.
    $product = Product::factory()->create(['workspace_id' => $workspace->id, 'name' => 'WIDGET']);
    TransactionProduct::create([
        'transaction_id' => $txn->id, 'product' => 'WIDGET', 'amount' => 5000,
    ]);
    $txn->chargeToUsers()->sync([$user->id => ['amount' => 5000]]);

    $this->actingAs($user)->post(isUrl($workspace), [
        'month' => '2026-05', 'cod_rate' => 0.02, 'vat_rate' => 0.12,
    ])->assertRedirect();

    $statement = IncomeStatement::first();

    expect((float) $statement->total_bought_cogs)->toBe(5000.0)
        ->and((float) $statement->productStatements()->where('product_id', $product->id)->value('total_bought_cogs'))->toBe(5000.0)
        ->and((float) $statement->userStatements()->where('user_id', $user->id)->value('total_bought_cogs'))->toBe(5000.0);
});

test('regenerate rewrites the statement it was asked for, whatever the date today', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    seedMay($workspace);

    // A February statement, saved back when the month was current.
    $february = IncomeStatement::create([
        'workspace_id' => $workspace->id,
        'period_month' => '2026-02-01',
        'cod_fee_rate' => 0.02,
        'vat_rate' => 0.12,
        'advisory_rate' => 0.30,
        'advisory_delivered_rate' => 0.09,
        'status' => 'final',
    ]);

    // Clicked on a day February doesn't have. Carbon fills the day from today
    // when parsing 'Y-m', so this used to resolve to March and rewrite that
    // statement instead.
    $this->travelTo('2026-08-31');

    $this->actingAs($user)
        ->post(isUrl($workspace, "/{$february->id}/regenerate"))
        ->assertRedirect();

    expect($february->fresh()->period_month->toDateString())->toBe('2026-02-01')
        // And no March statement was conjured up alongside it.
        ->and(IncomeStatement::where('workspace_id', $workspace->id)->count())->toBe(1);
});

test('regenerating an unchanged month reproduces the statement exactly', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['is_gencys_partner' => true]);
    seedMay($workspace);

    $this->actingAs($user)->post(isUrl($workspace), [
        'month' => '2026-05', 'cod_rate' => 0.02, 'vat_rate' => 0.12, 'advisory_rate' => 0.30,
    ])->assertRedirect();

    $statement = IncomeStatement::first();
    $tracked = collect($statement->getAttributes())
        ->except(['id', 'created_at', 'updated_at', 'generated_at'])
        ->all();

    $usersBefore = $statement->userStatements()->count();
    $productsBefore = $statement->productStatements()->count();

    // Nothing about the month has changed, so nothing about it should.
    $this->actingAs($user)
        ->post(isUrl($workspace, "/{$statement->id}/regenerate"))
        ->assertRedirect();

    $after = collect($statement->fresh()->getAttributes())
        ->except(['id', 'created_at', 'updated_at', 'generated_at'])
        ->all();

    expect($after)->toBe($tracked)
        // And the slices were rebuilt, not duplicated or dropped.
        ->and($statement->userStatements()->count())->toBe($usersBefore)
        ->and($statement->productStatements()->count())->toBe($productsBefore)
        ->and(IncomeStatement::count())->toBe(1);
});

test('opex is the outflow on types marked OPEX', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    seedMay($workspace);

    $account = Account::where('workspace_id', $workspace->id)->first();

    // Marked OPEX — counts.
    $rent = makeType($workspace, 'Rent and Utilities', section: 'opex');
    makeTxn($workspace, $account, $rent, 'out', 2500, '2026-05-09');

    // Marked cost of sales — belongs above gross profit, not here.
    $freight = makeType($workspace, 'Freight', section: 'cost_of_sales');
    makeTxn($workspace, $account, $freight, 'out', 400, '2026-05-09');

    // Excluded from the statement entirely.
    $excluded = makeType($workspace, 'Owner Drawings', section: null);
    makeTxn($workspace, $account, $excluded, 'out', 900, '2026-05-09');

    // An inflow on an OPEX type is money coming in, not an expense.
    makeTxn($workspace, $account, $rent, 'in', 700, '2026-05-09');

    // Outside the month.
    makeTxn($workspace, $account, $rent, 'out', 5000, '2026-04-09');

    $this->actingAs($user)->post(isUrl($workspace), [
        'month' => '2026-05', 'cod_rate' => 0.02, 'vat_rate' => 0.12,
    ])->assertRedirect();

    // seedMay's 1,000 + 500, plus the 2,500 rent. Nothing else.
    expect((float) IncomeStatement::first()->opex)->toBe(4000.0);
});

test('an untyped outflow is not counted as an expense', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    seedMay($workspace);

    $account = Account::where('workspace_id', $workspace->id)->first();

    // No transaction type at all — it isn't marked as anything, so guessing it
    // into OPEX would overstate expenses.
    makeTxn($workspace, $account, null, 'out', 3000, '2026-05-09');

    $this->actingAs($user)->post(isUrl($workspace), [
        'month' => '2026-05', 'cod_rate' => 0.02, 'vat_rate' => 0.12,
    ])->assertRedirect();

    expect((float) IncomeStatement::first()->opex)->toBe(1500.0);
});
