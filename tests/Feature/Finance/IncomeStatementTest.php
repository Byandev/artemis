<?php

use App\Models\Order;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Models\TransactionType;

function isUrl($workspace, string $path = ''): string
{
    return "/workspaces/{$workspace->slug}/finance/income-statements{$path}";
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

/**
 * Build a workspace with:
 *  - delivered revenue of 10,000 (2 delivered orders in May) + noise outside the window
 *  - out transactions: expenses 1,000 + transfer 500 in May; an out 200 outside May; an in 999
 */
function seedMay($workspace): array
{
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    $expenses = TransactionType::create(['workspace_id' => $workspace->id, 'name' => 'expenses']);
    $transfer = TransactionType::create(['workspace_id' => $workspace->id, 'name' => 'transfer']);

    // Delivered in May → counted (2 orders, 10,000)
    Order::factory()->forWorkspace($workspace)->delivered()->create(['delivered_at' => '2026-05-10 09:00:00', 'final_amount' => 6000]);
    Order::factory()->forWorkspace($workspace)->delivered()->create(['delivered_at' => '2026-05-20 09:00:00', 'final_amount' => 4000]);
    // Delivered outside May → excluded
    Order::factory()->forWorkspace($workspace)->delivered()->create(['delivered_at' => '2026-04-30 09:00:00', 'final_amount' => 3000]);
    // Not delivered → excluded
    Order::factory()->forWorkspace($workspace)->create(['delivered_at' => null, 'final_amount' => 7000]);

    makeTxn($workspace, $account, $expenses, 'out', 1000, '2026-05-15'); // counted
    makeTxn($workspace, $account, $transfer, 'out', 500, '2026-05-16');  // counted (until excluded)
    makeTxn($workspace, $account, $expenses, 'out', 200, '2026-04-15');  // outside month → excluded
    makeTxn($workspace, $account, $expenses, 'in', 999, '2026-05-17');   // inflow → excluded

    return compact('account', 'expenses', 'transfer');
}

test('preview computes delivered revenue and per-type expense buckets', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['expenses' => $expenses] = seedMay($workspace);

    $this->actingAs($user)
        ->get(isUrl($workspace, '/preview?month=2026-05'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/finance/income-statements/show')
            ->where('mode', 'preview')
            ->where('statement.delivered', fn ($v) => (float) $v === 10000.0)
            ->where('statement.orders', 2)
            ->has('statement.expenses', 2)
            // ordered by amount desc: expenses (1000) then transfer (500)
            ->where('statement.expenses.0.type_name', 'expenses')
            ->where('statement.expenses.0.amount', fn ($v) => (float) $v === 1000.0)
            ->where('statement.expenses.0.included', true)
            ->where('statement.expenses.1.type_name', 'transfer')
        );
});

test('store snapshots only the included types and computes net profit', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['expenses' => $expenses] = seedMay($workspace);

    $this->actingAs($user)
        ->post(isUrl($workspace), [
            'month' => '2026-05',
            'included_keys' => [$expenses->id], // exclude transfer
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('finance_income_statements', [
        'workspace_id' => $workspace->id,
        'total_delivered' => 10000,
        'delivered_orders' => 2,
        'total_expenses' => 1000,
        'net_profit' => 9000, // 10,000 − 1,000 (transfer excluded)
    ]);

    $this->assertDatabaseCount('finance_income_statement_expenses', 1);
    $this->assertDatabaseHas('finance_income_statement_expenses', ['type_name' => 'expenses', 'amount' => 1000]);
    $this->assertDatabaseMissing('finance_income_statement_expenses', ['type_name' => 'transfer']);
});

test('regenerate re-pulls amounts while keeping the same included types', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['account' => $account, 'expenses' => $expenses] = seedMay($workspace);

    $this->actingAs($user)->post(isUrl($workspace), [
        'month' => '2026-05',
        'included_keys' => [$expenses->id],
    ]);

    $statement = IncomeStatement::first();
    expect((float) $statement->total_expenses)->toBe(1000.0);

    // A new expense lands in the same type + month, then we regenerate.
    makeTxn($workspace, $account, $expenses, 'out', 250, '2026-05-25');

    $this->actingAs($user)
        ->post(isUrl($workspace, "/{$statement->id}/regenerate"))
        ->assertRedirect();

    $statement->refresh();
    expect((float) $statement->total_expenses)->toBe(1250.0);
    expect((float) $statement->net_profit)->toBe(8750.0);
    // Still only the one included type (transfer stays out).
    $this->assertDatabaseCount('finance_income_statement_expenses', 1);
});

test('a member without finance permission is forbidden', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $member = makeWorkspaceMember($workspace, 'member');

    $this->actingAs($member)
        ->get(isUrl($workspace))
        ->assertForbidden();
});
