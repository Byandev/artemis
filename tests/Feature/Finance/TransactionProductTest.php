<?php

use App\Models\Product;
use Modules\Finance\Models\Account;

// Charging a transaction to one or more products (with per-product shares) is
// covered by TransactionProductsTest. This file covers the product *suggestions*
// the form offers — the workspace's catalog products, the same list a fund
// request is built from, so an entry filled in from one lines up exactly.

test('the transactions page offers the workspace catalog products for the picker', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);

    Product::factory()->create([
        'workspace_id' => $workspace->id,
        'owner_id' => $user->id,
        'name' => 'Happy Heart Gel',
    ]);

    $this->actingAs($user)
        ->get("/workspaces/{$workspace->slug}/finance/transactions")
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/finance/transactions/index')
            ->where('products', fn ($products) => collect($products)->contains('Happy Heart Gel'))
        );
});

test('a product from another workspace is not suggested', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);

    ['user' => $stranger, 'workspace' => $elsewhere] = makeWorkspaceWithOwner();
    Product::factory()->create([
        'workspace_id' => $elsewhere->id,
        'owner_id' => $stranger->id,
        'name' => 'Someone Elses Product',
    ]);

    $this->actingAs($user)
        ->get("/workspaces/{$workspace->slug}/finance/transactions")
        ->assertInertia(fn ($page) => $page
            ->where('products', fn ($products) => ! collect($products)->contains('Someone Elses Product'))
        );
});
