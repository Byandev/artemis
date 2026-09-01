<?php

use App\Models\Department;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Transaction;

function tpUrl($workspace, string $path = ''): string
{
    return "/workspaces/{$workspace->slug}/finance/transactions{$path}";
}

test('the create page renders with the form option lists (incl. saved departments)', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    Department::create(['workspace_id' => $workspace->id, 'name' => 'Marketing', 'is_active' => true]);
    Department::create(['workspace_id' => $workspace->id, 'name' => 'Archived', 'is_active' => false]);

    $this->actingAs($user)
        ->get(tpUrl($workspace, "/create?account_id={$account->id}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/finance/transactions/create')
            ->where('defaultAccountId', $account->id)
            ->has('accounts')
            ->has('transactionTypes')
            ->has('users')
            ->has('products')
            // Only active departments are offered.
            ->where('departments', fn ($d) => collect($d)->contains('Marketing')
                && ! collect($d)->contains('Archived'))
        );
});

test('a transaction can be saved with a department', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    Department::create(['workspace_id' => $workspace->id, 'name' => 'Marketing', 'is_active' => true]);

    $this->actingAs($user)
        ->post(tpUrl($workspace), [
            'account_id' => $account->id,
            'date' => '2026-05-10',
            'description' => 'Ad spend',
            'type' => 'out',
            'amount' => 1000,
            'department' => 'Marketing',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('finance_transactions', [
        'workspace_id' => $workspace->id,
        'description' => 'Ad spend',
        'department' => 'Marketing',
    ]);
});

test('the edit page renders with the transaction', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    $txn = Transaction::create([
        'workspace_id' => $workspace->id,
        'account_id' => $account->id,
        'date' => '2026-05-10',
        'description' => 'Ad spend',
        'type' => 'out',
        'amount' => 1000,
    ]);

    $this->actingAs($user)
        ->get(tpUrl($workspace, "/{$txn->id}/edit"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/finance/transactions/edit')
            ->where('transaction.id', $txn->id)
            ->where('transaction.description', 'Ad spend')
        );
});

test('store redirects to the transactions list by default', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);

    $this->actingAs($user)
        ->post(tpUrl($workspace), [
            'account_id' => $account->id,
            'date' => '2026-05-10',
            'description' => 'Ad spend',
            'type' => 'out',
            'amount' => 1000,
        ])
        ->assertRedirect(tpUrl($workspace));
});

test('store honors a safe return_to path (e.g. the account page)', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    $accountPath = "/workspaces/{$workspace->slug}/finance/accounts/{$account->id}";

    $this->actingAs($user)
        ->post(tpUrl($workspace), [
            'account_id' => $account->id,
            'date' => '2026-05-10',
            'description' => 'Ad spend',
            'type' => 'out',
            'amount' => 1000,
            'return_to' => $accountPath,
        ])
        ->assertRedirect($accountPath);
});

test('store ignores a return_to that points outside the workspace', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);

    $this->actingAs($user)
        ->post(tpUrl($workspace), [
            'account_id' => $account->id,
            'date' => '2026-05-10',
            'description' => 'Ad spend',
            'type' => 'out',
            'amount' => 1000,
            'return_to' => 'https://evil.example.com/phish',
        ])
        ->assertRedirect(tpUrl($workspace));
});

test('the edit page carries the return_to it was opened with', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    $txn = Transaction::create([
        'workspace_id' => $workspace->id,
        'account_id' => $account->id,
        'date' => '2026-05-10',
        'description' => 'Ad spend',
        'type' => 'out',
        'amount' => 1000,
    ]);
    $listUrl = tpUrl($workspace, '?filter[search]=ad&page=2');

    $this->actingAs($user)
        ->get(tpUrl($workspace, "/{$txn->id}/edit?return_to=".urlencode($listUrl)))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('returnTo', $listUrl));
});

test('update returns to the filtered list the edit page came from', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    $txn = Transaction::create([
        'workspace_id' => $workspace->id,
        'account_id' => $account->id,
        'date' => '2026-05-10',
        'description' => 'Ad spend',
        'type' => 'out',
        'amount' => 1000,
    ]);
    $listUrl = tpUrl($workspace, '?filter[search]=ad&page=2');

    $this->actingAs($user)
        ->put(tpUrl($workspace, "/{$txn->id}"), [
            'account_id' => $account->id,
            'date' => '2026-05-10',
            'description' => 'Ad spend (edited)',
            'type' => 'out',
            'amount' => 1200,
            'return_to' => $listUrl,
        ])
        ->assertRedirect($listUrl);
});

test('update ignores a return_to that points outside the workspace', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    $txn = Transaction::create([
        'workspace_id' => $workspace->id,
        'account_id' => $account->id,
        'date' => '2026-05-10',
        'description' => 'Ad spend',
        'type' => 'out',
        'amount' => 1000,
    ]);

    $this->actingAs($user)
        ->put(tpUrl($workspace, "/{$txn->id}"), [
            'account_id' => $account->id,
            'date' => '2026-05-10',
            'description' => 'Ad spend (edited)',
            'type' => 'out',
            'amount' => 1200,
            'return_to' => 'https://evil.example.com/phish',
        ])
        ->assertRedirect(tpUrl($workspace));
});
