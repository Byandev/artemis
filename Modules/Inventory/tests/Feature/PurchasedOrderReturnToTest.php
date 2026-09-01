<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Models\PurchasedOrderItem;
use Tests\TestCase;

// Module test dirs aren't bound by the root tests/Pest.php (->in('Feature') only
// covers tests/Feature), so extend the app TestCase explicitly to boot the app.
uses(TestCase::class, RefreshDatabase::class);

function poUrl($workspace, string $path = ''): string
{
    return "/workspaces/{$workspace->slug}/inventory/purchased-orders{$path}";
}

/** A saveable PO (one line item) plus the payload the edit form posts back. */
function makeOrderForEdit($workspace): array
{
    $item = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-'.uniqid(),
        'is_active' => true,
    ]);

    $order = PurchasedOrder::create([
        'workspace_id' => $workspace->id,
        'issue_date' => '2026-06-01',
        'delivery_fee' => 0,
        'total_amount' => 100,
        'status' => 1,
    ]);

    PurchasedOrderItem::create([
        'inventory_purchased_order_id' => $order->id,
        'inventory_item_id' => $item->id,
        'count' => 1,
        'amount' => 100,
        'total_amount' => 100,
    ]);

    return [$order, [
        'issue_date' => '2026-06-02',
        'delivery_fee' => 0,
        'total_amount' => 150,
        'status' => 1,
        'items' => [[
            'inventory_item_id' => $item->id,
            'count' => 1,
            'amount' => 150,
            'total_amount' => 150,
        ]],
    ]];
}

test('the edit page carries the return_to it was opened with', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    [$order] = makeOrderForEdit($workspace);
    $listUrl = poUrl($workspace, '?filter[status]=1&page=2');

    $this->actingAs($user)
        ->get(poUrl($workspace, "/{$order->id}/edit?return_to=".urlencode($listUrl)))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/inventory/purchased-orders/edit')
            ->where('returnTo', $listUrl)
        );
});

test('update returns to the filtered list the edit page came from', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    [$order, $payload] = makeOrderForEdit($workspace);
    $listUrl = poUrl($workspace, '?filter[status]=1&page=2');

    $this->actingAs($user)
        ->put(poUrl($workspace, "/{$order->id}"), [...$payload, 'return_to' => $listUrl])
        ->assertRedirect($listUrl);

    expect($order->fresh()->total_amount)->toEqual(150);
});

test('update ignores a return_to that points outside the workspace', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    [$order, $payload] = makeOrderForEdit($workspace);

    $this->actingAs($user)
        ->put(poUrl($workspace, "/{$order->id}"), [...$payload, 'return_to' => 'https://evil.example.com/phish'])
        ->assertRedirect(poUrl($workspace));
});
