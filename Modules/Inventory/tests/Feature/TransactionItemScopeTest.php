<?php

use App\Models\Product;
use App\Models\Shop;
use App\Models\Team;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Inventory\Models\InventoryItem;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function transactionScopeItemForTeam(Workspace $workspace, ?Team $team, string $sku): InventoryItem
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

test('the transaction item picker is scoped to the active team', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $teamA = Team::factory()->create(['workspace_id' => $workspace->id]);
    $teamB = Team::factory()->create(['workspace_id' => $workspace->id]);

    $itemA = transactionScopeItemForTeam($workspace, $teamA, 'A-1');
    $itemB = transactionScopeItemForTeam($workspace, $teamB, 'B-1');

    $this->actingAs($owner)
        ->get(route('inventory.transactions.index', ['workspace' => $workspace, 'team_id' => $teamA->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.id', $itemA->id)
        );

    expect($itemB->exists)->toBeTrue();
});

test('the transaction item picker shows every item when no team is selected', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $teamA = Team::factory()->create(['workspace_id' => $workspace->id]);
    $teamB = Team::factory()->create(['workspace_id' => $workspace->id]);

    transactionScopeItemForTeam($workspace, $teamA, 'A-1');
    transactionScopeItemForTeam($workspace, $teamB, 'B-1');

    $this->actingAs($owner)
        ->get(route('inventory.transactions.index', $workspace))
        ->assertInertia(fn (Assert $page) => $page->has('items', 2));
});
