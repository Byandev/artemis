<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Inventory\Models\InventoryItem;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** A Gencys-partner workspace with ERP credentials on it. */
function erpReadyWorkspace(): array
{
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->update([
        'is_gencys_partner' => true,
        'erp_username' => 'erp-user',
        'erp_password' => 'erp-pass',
    ]);

    return ['user' => $user, 'workspace' => $workspace];
}

test('the sync posts the ERP credentials to n8n and saves the items it answers with', function () {
    config(['services.n8n.gencys_inventory_items_webhook_url' => 'https://n8n.test/webhook/inventory-items']);
    Http::fake(['n8n.test/*' => Http::response([
        ['id' => 401, 'name' => 'SKU-ALPHA'],
        ['id' => 402, 'name' => 'SKU-BETA'],
    ], 200)]);

    ['user' => $user, 'workspace' => $workspace] = erpReadyWorkspace();

    $this->actingAs($user)
        ->post(route('workspaces.inventory.item.sync-erp', $workspace))
        ->assertRedirect()
        ->assertSessionHas('success');

    Http::assertSent(fn ($request) => $request->url() === 'https://n8n.test/webhook/inventory-items'
        && $request['erp_username'] === 'erp-user'
        && $request['erp_password'] === 'erp-pass');

    expect(InventoryItem::where('workspace_id', $workspace->id)->pluck('reference_id', 'sku')->all())
        ->toBe(['SKU-ALPHA' => 401, 'SKU-BETA' => 402]);
});

test('re-syncing updates the existing item rather than duplicating its SKU', function () {
    config(['services.n8n.gencys_inventory_items_webhook_url' => 'https://n8n.test/webhook/inventory-items']);
    Http::fake(['n8n.test/*' => Http::response([['id' => 555, 'name' => 'SKU-ALPHA']], 200)]);

    ['user' => $user, 'workspace' => $workspace] = erpReadyWorkspace();

    $existing = InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-ALPHA',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('workspaces.inventory.item.sync-erp', $workspace))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(InventoryItem::where('workspace_id', $workspace->id)->count())->toBe(1)
        ->and($existing->fresh()->reference_id)->toBe(555);
});

test('the sync fails cleanly when the workspace has no ERP credentials', function () {
    config(['services.n8n.gencys_inventory_items_webhook_url' => 'https://n8n.test/webhook/inventory-items']);
    Http::fake();

    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $workspace->update(['is_gencys_partner' => true]);

    $this->actingAs($user)
        ->post(route('workspaces.inventory.item.sync-erp', $workspace))
        ->assertRedirect()
        ->assertSessionHas('error');

    Http::assertNothingSent();
});

test('the sync reports back when n8n rejects the call', function () {
    config(['services.n8n.gencys_inventory_items_webhook_url' => 'https://n8n.test/webhook/inventory-items']);
    Http::fake(['n8n.test/*' => Http::response('workflow not active', 500)]);

    ['user' => $user, 'workspace' => $workspace] = erpReadyWorkspace();

    $this->actingAs($user)
        ->post(route('workspaces.inventory.item.sync-erp', $workspace))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(InventoryItem::where('workspace_id', $workspace->id)->count())->toBe(0);
});

test('non-partner workspaces cannot trigger the ERP sync', function () {
    config(['services.n8n.gencys_inventory_items_webhook_url' => 'https://n8n.test/webhook/inventory-items']);
    Http::fake();

    ['user' => $user, 'workspace' => $workspace] = erpReadyWorkspace();
    $workspace->update(['is_gencys_partner' => false]);

    $this->actingAs($user)
        ->post(route('workspaces.inventory.item.sync-erp', $workspace))
        ->assertForbidden();

    Http::assertNothingSent();
});
