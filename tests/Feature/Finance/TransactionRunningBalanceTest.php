<?php

use Modules\Finance\Models\Account;
use Modules\Finance\Models\Transaction;

function rbUrl($workspace, string $path = ''): string
{
    return "/workspaces/{$workspace->slug}/finance/transactions{$path}";
}

function rbTxn($workspace, $account, array $attrs = []): Transaction
{
    return Transaction::create(array_merge([
        'workspace_id' => $workspace->id,
        'account_id' => $account->id,
        'date' => '2026-05-10',
        'description' => 'Entry',
        'type' => 'out',
        'amount' => 100,
        'position' => 1,
    ], $attrs));
}

/** The account option the form uses to carry the balance forward. */
function rbOption($accounts, $account): array
{
    return collect($accounts)->firstWhere('id', $account->id);
}

test('an account with no transactions offers its opening balance', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create([
        'workspace_id' => $workspace->id,
        'name' => 'Cash',
        'opening_balance' => 5000,
    ]);

    $this->actingAs($user)
        ->get(rbUrl($workspace, '/create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('accounts', fn ($accounts) => (float) rbOption($accounts, $account)['current_balance'] === 5000.0
                && rbOption($accounts, $account)['has_transactions'] === false)
        );
});

test('an account with transactions offers its latest running balance', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create([
        'workspace_id' => $workspace->id,
        'name' => 'Cash',
        'opening_balance' => 5000,
    ]);

    rbTxn($workspace, $account, ['date' => '2026-05-10', 'running_balance' => 4900]);
    rbTxn($workspace, $account, ['date' => '2026-05-12', 'running_balance' => 4700]);
    // Older date, higher position — must not win over the 05-12 entry.
    rbTxn($workspace, $account, ['date' => '2026-05-11', 'position' => 9, 'running_balance' => 1]);

    $this->actingAs($user)
        ->get(rbUrl($workspace, '/create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('accounts', fn ($accounts) => (float) rbOption($accounts, $account)['current_balance'] === 4700.0
                && rbOption($accounts, $account)['has_transactions'] === true)
        );
});

test('editing the newest entry carries from the one before it, not from itself', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create([
        'workspace_id' => $workspace->id,
        'name' => 'Cash',
        'opening_balance' => 5000,
    ]);

    rbTxn($workspace, $account, ['date' => '2026-05-10', 'running_balance' => 4900]);
    $newest = rbTxn($workspace, $account, ['date' => '2026-05-12', 'running_balance' => 4700]);

    $this->actingAs($user)
        ->get(rbUrl($workspace, "/{$newest->id}/edit"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('accounts', fn ($accounts) => (float) rbOption($accounts, $account)['current_balance'] === 4900.0)
        );
});

test('editing the only entry on an account falls back to the opening balance', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create([
        'workspace_id' => $workspace->id,
        'name' => 'Cash',
        'opening_balance' => 5000,
    ]);

    $only = rbTxn($workspace, $account, ['running_balance' => 4900]);

    $this->actingAs($user)
        ->get(rbUrl($workspace, "/{$only->id}/edit"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('accounts', fn ($accounts) => (float) rbOption($accounts, $account)['current_balance'] === 5000.0
                && rbOption($accounts, $account)['has_transactions'] === false)
        );
});

test('each account carries its own balance forward', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $cash = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash', 'opening_balance' => 5000]);
    $bank = Account::create(['workspace_id' => $workspace->id, 'name' => 'Bank', 'opening_balance' => 200]);

    rbTxn($workspace, $cash, ['running_balance' => 4900]);

    $this->actingAs($user)
        ->get(rbUrl($workspace, '/create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('accounts', fn ($accounts) => (float) rbOption($accounts, $cash)['current_balance'] === 4900.0
                && (float) rbOption($accounts, $bank)['current_balance'] === 200.0)
        );
});
