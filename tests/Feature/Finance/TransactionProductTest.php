<?php

use Modules\Finance\Models\Account;
use Modules\Finance\Models\Transaction;
use Modules\GencysERP\Models\GencysDailySalesOrder;

function txnUrl($workspace, string $path = ''): string
{
    return "/workspaces/{$workspace->slug}/finance/transactions{$path}";
}

test('a transaction can be assigned a product on create', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);

    $this->actingAs($user)
        ->post(txnUrl($workspace), [
            'account_id' => $account->id,
            'date' => '2026-05-10',
            'description' => 'Ad spend',
            'type' => 'out',
            'amount' => 1000,
            'product' => 'WIDGET',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('finance_transactions', [
        'workspace_id' => $workspace->id,
        'description' => 'Ad spend',
        'product' => 'WIDGET',
    ]);
});

test('a transaction product can be changed on update', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    $txn = Transaction::create([
        'workspace_id' => $workspace->id,
        'account_id' => $account->id,
        'date' => '2026-05-10',
        'description' => 'Ad spend',
        'type' => 'out',
        'amount' => 1000,
        'product' => 'WIDGET',
    ]);

    $this->actingAs($user)
        ->put(txnUrl($workspace, "/{$txn->id}"), [
            'account_id' => $account->id,
            'date' => '2026-05-10',
            'description' => 'Ad spend',
            'type' => 'out',
            'amount' => 1000,
            'product' => 'GADGET',
        ])
        ->assertRedirect();

    expect($txn->fresh()->product)->toBe('GADGET');
});

test('the transactions page lists normalized products for the picker', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);

    // The quantity prefix ("2X ") is stripped and the name upper-cased, so both
    // rows collapse to a single "WIDGET" suggestion.
    GencysDailySalesOrder::create(['id' => 900001, 'workspace_id' => $workspace->id, 'order_details' => '1X WIDGET', 'parcel_status' => 'DELIVERED', 'price_final' => 0, 'shipping_fee' => 0]);
    GencysDailySalesOrder::create(['id' => 900002, 'workspace_id' => $workspace->id, 'order_details' => '2X widget', 'parcel_status' => 'DELIVERED', 'price_final' => 0, 'shipping_fee' => 0]);

    $this->actingAs($user)
        ->get(txnUrl($workspace))
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/finance/transactions/index')
            ->where('products', fn ($products) => collect($products)->contains('WIDGET'))
        );
});
