<?php

use App\Models\Shop;
use App\Models\Team;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Inventory\Models\InventoryItem;
use Modules\Products\Models\Product;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function purchasedOrderScopeItemForTeam(Workspace $workspace, ?Team $team, string $sku): InventoryItem
{
    $product = Product::factory()->create(['workspace_id' => $workspace->id]);
    $shop = Shop::factory()->forWorkspace($workspace)->create(['product_id' => $product->id]);

    if ($team) {
        $shop->teams()->attach($team->id);
    }

    return InventoryItem::create([
        'workspace_id' => $workspace->id,
        'product_id' => $product->id,
        'sku' => $sku,
        'is_active' => true,
        'is_parent' => false,
    ]);
}

test('the add-order item picker is scoped to the active team', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $teamA = Team::factory()->create(['workspace_id' => $workspace->id]);
    $teamB = Team::factory()->create(['workspace_id' => $workspace->id]);

    $itemA = purchasedOrderScopeItemForTeam($workspace, $teamA, 'A-1');
    purchasedOrderScopeItemForTeam($workspace, $teamB, 'B-1');

    $this->actingAs($owner)
        ->get(route('workspaces.inventory.purchased-orders.create', ['workspace' => $workspace, 'team_id' => $teamA->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.id', $itemA->id)
        );
});

test('the add-order item picker shows every item when no team is selected', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $teamA = Team::factory()->create(['workspace_id' => $workspace->id]);
    $teamB = Team::factory()->create(['workspace_id' => $workspace->id]);

    purchasedOrderScopeItemForTeam($workspace, $teamA, 'A-1');
    purchasedOrderScopeItemForTeam($workspace, $teamB, 'B-1');

    $this->actingAs($owner)
        ->get(route('workspaces.inventory.purchased-orders.create', $workspace))
        ->assertInertia(fn (Assert $page) => $page->has('items', 2));
});
