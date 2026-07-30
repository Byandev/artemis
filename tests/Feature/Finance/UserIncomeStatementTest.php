<?php

use Modules\Finance\Models\Account;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Models\TransactionType;
use Modules\Finance\Models\UserIncomeStatement;
use Modules\GencysERP\Models\GencysDailySalesOrder;
use Modules\GencysERP\Models\Intern;

function uisUrl($workspace, string $path = ''): string
{
    return "/workspaces/{$workspace->slug}/finance/user-income-statements{$path}";
}

function uisOrder($workspace, array $attrs = []): GencysDailySalesOrder
{
    static $id = 700000;

    return GencysDailySalesOrder::create(array_merge([
        'id' => $id++,
        'workspace_id' => $workspace->id,
        'parcel_status' => 'DELIVERED',
        'price_final' => 0,
        'shipping_fee' => 0,
        'total_cog' => 0,
    ], $attrs));
}

/**
 * May 2026, intern Juan (linked to $user). Two WIDGET orders (rev 8,000, cogs
 * 1,600, ship 300) + one GADGET (rev 4,000, cogs 800, ship 150). Transactions
 * charged to Juan: Ad Spent 800 tagged WIDGET, 300 tagged GADGET, Salary 500
 * untagged. A Maria order that must be excluded. COD 2%, VAT 12%, advisory 30%.
 */
function seedJuan($workspace, $user): Intern
{
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    $adSpent = TransactionType::create(['workspace_id' => $workspace->id, 'name' => 'Ad Spent']);
    $salary = TransactionType::create(['workspace_id' => $workspace->id, 'name' => 'Salary']);

    $juan = Intern::create([
        'workspace_id' => $workspace->id,
        'intern_id' => 1,
        'full_name' => 'Juan Dela Cruz',
        'active' => true,
        'user_id' => $user->id,
    ]);

    // Juan's delivered orders (intern_brands_name resolves to Juan via full_name).
    uisOrder($workspace, ['intern_brands_name' => 'Juan Dela Cruz - Acme', 'order_details' => '1X WIDGET', 'price_final' => 5000, 'total_cog' => 1000, 'shipping_fee' => 200, 'parcel_updated_date' => '2026-05-10 09:00:00', 'shipped_out_date' => '2026-05-05']);
    uisOrder($workspace, ['intern_brands_name' => 'Juan Dela Cruz - Acme', 'order_details' => '2X WIDGET', 'price_final' => 3000, 'total_cog' => 600, 'shipping_fee' => 100, 'parcel_updated_date' => '2026-05-20 09:00:00', 'shipped_out_date' => '2026-05-08']);
    uisOrder($workspace, ['intern_brands_name' => 'Juan Dela Cruz - Acme', 'order_details' => '1X GADGET', 'price_final' => 4000, 'total_cog' => 800, 'shipping_fee' => 150, 'parcel_updated_date' => '2026-05-15 09:00:00', 'shipped_out_date' => '2026-05-09']);
    // Different intern — must be excluded from Juan's statement.
    uisOrder($workspace, ['intern_brands_name' => 'Maria Santos - Beta', 'order_details' => '1X WIDGET', 'price_final' => 9999, 'total_cog' => 999, 'shipping_fee' => 999, 'parcel_updated_date' => '2026-05-12 09:00:00', 'shipped_out_date' => '2026-05-07']);

    // Transactions charged to Juan's user, who bears the whole of each.
    $chargeToJuan = function (array $attrs) use ($workspace, $account, $user) {
        $txn = Transaction::create(array_merge([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'type' => 'out',
        ], $attrs));

        $txn->chargeToUsers()->sync([$user->id => ['amount' => $attrs['amount']]]);
    };

    $chargeToJuan(['date' => '2026-05-14', 'description' => 'Ads', 'transaction_type_id' => $adSpent->id, 'product' => 'WIDGET', 'amount' => 800]);
    $chargeToJuan(['date' => '2026-05-16', 'description' => 'Ads', 'transaction_type_id' => $adSpent->id, 'product' => 'GADGET', 'amount' => 300]);
    $chargeToJuan(['date' => '2026-05-15', 'description' => 'Pay', 'transaction_type_id' => $salary->id, 'product' => null, 'amount' => 500]);

    return $juan;
}

test('preview computes per-product gross with tagged txns folded in and untagged as OPEX', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['is_gencys_partner' => true]);
    $juan = seedJuan($workspace, $user);

    $this->actingAs($user)
        ->get(uisUrl($workspace, "/preview?intern_id={$juan->id}&month=2026-05"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/finance/user-income-statements/show')
            ->where('statement.intern_name', 'Juan Dela Cruz')
            ->where('statement.delivered', fn ($v) => (float) $v === 12000.0)
            ->where('statement.orders', 3)
            // WIDGET gross = 8000 − (1600 + 300 + 160 + 19.20 + 800) = 5120.80
            ->where('statement.products', fn ($rows) => collect($rows)->contains(
                fn ($r) => $r['product'] === 'WIDGET' && (float) $r['gross_profit'] === 5120.80
            ))
            // GADGET gross = 4000 − (800 + 150 + 80 + 9.60 + 300) = 2660.40
            ->where('statement.products', fn ($rows) => collect($rows)->contains(
                fn ($r) => $r['product'] === 'GADGET' && (float) $r['gross_profit'] === 2660.40
            ))
            ->where('statement.gross_profit', fn ($v) => (float) $v === 7781.20)
            // Only the untagged Salary is OPEX.
            ->where('statement.total_opex', fn ($v) => (float) $v === 500.0)
            ->where('statement.opex', fn ($rows) => collect($rows)->contains(
                fn ($r) => $r['type_name'] === 'Salary' && (float) $r['amount'] === 500.0
            ))
            // Advisory = 30% × 7781.20 = 2334.36 ; Net = 7781.20 − 500 − 2334.36
            ->where('statement.advisory_share', fn ($v) => (float) $v === 2334.36)
            ->where('statement.net_profit', fn ($v) => (float) $v === 4946.84)
        );
});

test('store snapshots the header, product rows and opex buckets', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['is_gencys_partner' => true]);
    $juan = seedJuan($workspace, $user);

    $this->actingAs($user)
        ->post(uisUrl($workspace), ['intern_id' => $juan->id, 'month' => '2026-05'])
        ->assertRedirect();

    $this->assertDatabaseHas('finance_user_income_statements', [
        'workspace_id' => $workspace->id,
        'gencys_intern_id' => $juan->id,
        'intern_name' => 'Juan Dela Cruz',
        'total_delivered' => 12000,
        'gross_profit' => 7781.20,
        'total_opex' => 500,
        'advisory_share' => 2334.36,
        'net_profit' => 4946.84,
    ]);

    $statement = UserIncomeStatement::first();
    $this->assertDatabaseHas('finance_user_income_statement_products', [
        'user_income_statement_id' => $statement->id,
        'product' => 'WIDGET',
        'tagged_expense' => 800,
        'gross_profit' => 5120.80,
    ]);
    $this->assertDatabaseHas('finance_user_income_statement_expenses', [
        'user_income_statement_id' => $statement->id,
        'type_name' => 'Salary',
        'amount' => 500,
    ]);
    // The tagged Ad Spent lines are NOT in OPEX (they went into product rows).
    $this->assertDatabaseMissing('finance_user_income_statement_expenses', [
        'user_income_statement_id' => $statement->id,
        'type_name' => 'Ad Spent',
    ]);
});

test('non-partner workspaces get no advisory share', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $juan = seedJuan($workspace, $user); // is_gencys_partner defaults false

    $this->actingAs($user)
        ->post(uisUrl($workspace), ['intern_id' => $juan->id, 'month' => '2026-05'])
        ->assertRedirect();

    $this->assertDatabaseHas('finance_user_income_statements', [
        'gencys_intern_id' => $juan->id,
        'advisory_share' => 0,
        // Net = gross 7781.20 − opex 500 − advisory 0
        'net_profit' => 7281.20,
    ]);
});

test('a member without finance permission is forbidden', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = makeWorkspaceMember($workspace, 'member');

    $this->actingAs($member)
        ->get(uisUrl($workspace))
        ->assertForbidden();
});
