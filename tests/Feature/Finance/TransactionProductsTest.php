<?php

use Modules\Finance\Models\Account;
use Modules\Finance\Models\Transaction;

function tprUrl($workspace, string $path = ''): string
{
    return "/workspaces/{$workspace->slug}/finance/transactions{$path}";
}

/** A transaction payload with everything but the product rows filled in. */
function tprPayload($account, array $attrs = []): array
{
    return array_merge([
        'account_id' => $account->id,
        'date' => '2026-05-10',
        'description' => 'Ad spend',
        'type' => 'out',
        'amount' => 900,
    ], $attrs);
}

test('a transaction can be charged to one product, which bears the whole amount', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);

    $this->actingAs($user)
        ->post(tprUrl($workspace), tprPayload($account, [
            'products' => [['product' => 'WIDGET', 'amount' => null]],
        ]))
        ->assertRedirect();

    $this->assertDatabaseHas('finance_transaction_products', [
        'transaction_id' => Transaction::first()->id,
        'product' => 'WIDGET',
        'amount' => 900,
    ]);
});

test('blank shares split the amount evenly between the charged products', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);

    $this->actingAs($user)
        ->post(tprUrl($workspace), tprPayload($account, [
            'products' => [
                ['product' => 'WIDGET'],
                ['product' => 'GADGET'],
                ['product' => 'SPROCKET'],
            ],
        ]))
        ->assertRedirect();

    $shares = Transaction::first()->productShares
        ->pluck('amount', 'product')
        ->map(fn ($a) => (float) $a);

    expect($shares['WIDGET'])->toBe(300.0)
        ->and($shares['GADGET'])->toBe(300.0)
        ->and($shares['SPROCKET'])->toBe(300.0);
});

test('an uneven split keeps every centavo, the odd ones going to the first product', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);

    $this->actingAs($user)
        ->post(tprUrl($workspace), tprPayload($account, [
            'amount' => 100,
            'products' => [
                ['product' => 'WIDGET'],
                ['product' => 'GADGET'],
                ['product' => 'SPROCKET'],
            ],
        ]))
        ->assertRedirect();

    $shares = Transaction::first()->productShares
        ->pluck('amount', 'product')
        ->map(fn ($a) => (float) $a);

    expect($shares['WIDGET'])->toBe(33.34)
        ->and($shares['GADGET'])->toBe(33.33)
        ->and($shares['SPROCKET'])->toBe(33.33)
        ->and(round($shares->sum(), 2))->toBe(100.0);
});

test('product shares can be set by hand when the split is not even', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);

    $this->actingAs($user)
        ->post(tprUrl($workspace), tprPayload($account, [
            'products' => [
                ['product' => 'WIDGET', 'amount' => 600],
                ['product' => 'GADGET', 'amount' => 300],
            ],
        ]))
        ->assertRedirect();

    $shares = Transaction::first()->productShares
        ->pluck('amount', 'product')
        ->map(fn ($a) => (float) $a);

    expect($shares['WIDGET'])->toBe(600.0)
        ->and($shares['GADGET'])->toBe(300.0);
});

test('a blank product share takes whatever the explicit shares left over', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);

    $this->actingAs($user)
        ->post(tprUrl($workspace), tprPayload($account, [
            'products' => [
                ['product' => 'WIDGET', 'amount' => 700],
                ['product' => 'GADGET'],
            ],
        ]))
        ->assertRedirect();

    $shares = Transaction::first()->productShares
        ->pluck('amount', 'product')
        ->map(fn ($a) => (float) $a);

    expect($shares['GADGET'])->toBe(200.0);
});

test('product shares that do not add up to the amount are rejected', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);

    $this->actingAs($user)
        ->post(tprUrl($workspace), tprPayload($account, [
            'products' => [
                ['product' => 'WIDGET', 'amount' => 100],
                ['product' => 'GADGET', 'amount' => 100],
            ],
        ]))
        ->assertSessionHasErrors('products');

    $this->assertDatabaseCount('finance_transaction_products', 0);
});

test('the same product cannot be charged twice on one transaction', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);

    $this->actingAs($user)
        ->post(tprUrl($workspace), tprPayload($account, [
            'products' => [
                ['product' => 'WIDGET', 'amount' => 450],
                ['product' => 'WIDGET', 'amount' => 450],
            ],
        ]))
        ->assertSessionHasErrors('products.0.product');
});

test('updating replaces the charged products and their shares', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);

    $txn = Transaction::create([
        'workspace_id' => $workspace->id,
        'account_id' => $account->id,
        'date' => '2026-05-10',
        'description' => 'Ad spend',
        'type' => 'out',
        'amount' => 900,
    ]);
    $txn->productShares()->create(['product' => 'WIDGET', 'amount' => 900]);

    $this->actingAs($user)
        ->put(tprUrl($workspace, "/{$txn->id}"), tprPayload($account, [
            'products' => [['product' => 'GADGET']],
        ]))
        ->assertRedirect();

    $this->assertDatabaseMissing('finance_transaction_products', [
        'transaction_id' => $txn->id,
        'product' => 'WIDGET',
    ]);
    $this->assertDatabaseHas('finance_transaction_products', [
        'transaction_id' => $txn->id,
        'product' => 'GADGET',
        'amount' => 900,
    ]);
});

test('clearing the product list drops every share', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);

    $txn = Transaction::create([
        'workspace_id' => $workspace->id,
        'account_id' => $account->id,
        'date' => '2026-05-10',
        'description' => 'Ad spend',
        'type' => 'out',
        'amount' => 900,
    ]);
    $txn->productShares()->create(['product' => 'WIDGET', 'amount' => 900]);

    $this->actingAs($user)
        ->put(tprUrl($workspace, "/{$txn->id}"), tprPayload($account))
        ->assertRedirect();

    $this->assertDatabaseCount('finance_transaction_products', 0);
});

test('the edit page ships the charged products with their shares', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);

    $txn = Transaction::create([
        'workspace_id' => $workspace->id,
        'account_id' => $account->id,
        'date' => '2026-05-10',
        'description' => 'Ad spend',
        'type' => 'out',
        'amount' => 900,
    ]);
    $txn->productShares()->createMany([
        ['product' => 'WIDGET', 'amount' => 600],
        ['product' => 'GADGET', 'amount' => 300],
    ]);

    $this->actingAs($user)
        ->get(tprUrl($workspace, "/{$txn->id}/edit"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('transaction.product_shares', 2)
            ->where('transaction.product_shares', fn ($rows) => collect($rows)->contains(
                fn ($r) => $r['product'] === 'GADGET' && (float) $r['amount'] === 300.0
            ))
        );
});
