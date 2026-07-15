<?php

use App\Models\Product;
use App\Models\Role;
use App\Models\Shop;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryTransaction;
use Modules\Inventory\Models\InventoryUnitCode;
use Modules\Inventory\Models\InventoryUnitCodeItem;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Models\PurchasedOrderItem;

/** A workspace member with a scoped role (no "View All Workspace Data") on the given teams. */
function scopedInventoryMember(Workspace $workspace, array $teams = []): User
{
    $user = User::factory()->create();
    $role = Role::factory()->create(['workspace_id' => $workspace->id]);
    $user->workspaces()->attach($workspace, ['role_id' => $role->id]);

    foreach ($teams as $team) {
        $user->teams()->attach($team);
    }

    return $user;
}

/**
 * Build the full inventory chain (product → shop(teams) → item → transaction, PO,
 * unit code, sync run) for a set of teams. The unit code links to the item by SKU.
 *
 * @param  array<int, Team>  $teams
 * @return array<string, mixed>
 */
function inventoryChainForTeams(Workspace $workspace, array $teams, string $sku): array
{
    $product = Product::factory()->create(['workspace_id' => $workspace->id]);
    $shop = Shop::factory()->forWorkspace($workspace)->create(['product_id' => $product->id]);

    if ($teams) {
        $shop->teams()->attach(collect($teams)->pluck('id')->all());
    }

    $item = InventoryItem::create([
        'workspace_id' => $workspace->id, 'product_id' => $product->id,
        'sku' => $sku, 'is_active' => true, 'is_parent' => false,
    ]);

    $tx = InventoryTransaction::create([
        'workspace_id' => $workspace->id, 'inventory_item_id' => $item->id,
        'date' => '2026-07-01', 'ref_no' => "REF-{$sku}",
    ]);

    $po = PurchasedOrder::create(['workspace_id' => $workspace->id, 'issue_date' => '2026-07-01', 'status' => 1]);
    PurchasedOrderItem::create(['inventory_purchased_order_id' => $po->id, 'inventory_item_id' => $item->id, 'count' => 5]);

    $uc = InventoryUnitCode::create(['workspace_id' => $workspace->id, 'unit_code' => "{$sku}-UC", 'sku' => $sku]);
    InventoryUnitCodeItem::create(['workspace_id' => $workspace->id, 'unit_code' => "{$sku}-UC", 'item_code' => $sku, 'quantity' => 2]);

    $gsr = GencysSyncRun::create([
        'workspace_id' => $workspace->id, 'inventory_item_id' => $item->id,
        'sync_type' => GencysSyncRun::TYPE_TRANSACTION_HISTORY, 'status' => 'success',
    ]);

    return compact('item', 'tx', 'po', 'uc', 'gsr');
}

it('scopes every inventory feature to the teams a user belongs to', function () {
    $workspace = Workspace::factory()->create();
    $teamA = Team::factory()->create(['workspace_id' => $workspace->id]);
    $teamB = Team::factory()->create(['workspace_id' => $workspace->id]);

    $a = inventoryChainForTeams($workspace, [$teamA], 'A-1');
    $b = inventoryChainForTeams($workspace, [$teamB], 'B-1');

    // An unlinked item (no product) — no path to any team.
    $orphan = InventoryItem::create([
        'workspace_id' => $workspace->id, 'product_id' => null,
        'sku' => 'ORPHAN', 'is_active' => true, 'is_parent' => false,
    ]);

    $user = scopedInventoryMember($workspace, [$teamA]);

    $visible = fn ($model) => $model::query()->visibleTo($user, $workspace)->pluck('id')->all();

    // Each feature: sees team A's record, not team B's.
    expect($visible(InventoryItem::class))->toContain($a['item']->id)
        ->not->toContain($b['item']->id)
        ->not->toContain($orphan->id);
    expect($visible(InventoryTransaction::class))->toContain($a['tx']->id)->not->toContain($b['tx']->id);
    expect($visible(PurchasedOrder::class))->toContain($a['po']->id)->not->toContain($b['po']->id);
    expect($visible(InventoryUnitCode::class))->toContain($a['uc']->id)->not->toContain($b['uc']->id);
    expect($visible(GencysSyncRun::class))->toContain($a['gsr']->id)->not->toContain($b['gsr']->id);
});

it('scopes the product picker to the teams a user belongs to', function () {
    $workspace = Workspace::factory()->create();
    $teamA = Team::factory()->create(['workspace_id' => $workspace->id]);
    $teamB = Team::factory()->create(['workspace_id' => $workspace->id]);

    $a = inventoryChainForTeams($workspace, [$teamA], 'A-1');
    $b = inventoryChainForTeams($workspace, [$teamB], 'B-1');

    // A product with no shop -> no path to any team.
    $orphanProduct = Product::factory()->create(['workspace_id' => $workspace->id]);

    $user = scopedInventoryMember($workspace, [$teamA]);

    $visibleProductIds = Product::query()->visibleTo($user, $workspace)->pluck('id')->all();

    expect($visibleProductIds)
        ->toContain($a['item']->product_id)
        ->not->toContain($b['item']->product_id)
        ->not->toContain($orphanProduct->id);
});

it('shows all inventory records to the workspace owner (unrestricted)', function () {
    $workspace = Workspace::factory()->create();
    $owner = User::find($workspace->owner_id);
    $teamA = Team::factory()->create(['workspace_id' => $workspace->id]);

    $a = inventoryChainForTeams($workspace, [$teamA], 'A-1');
    $b = inventoryChainForTeams($workspace, [], 'B-1'); // shop on no team

    $visible = fn ($model) => $model::query()->visibleTo($owner, $workspace)->pluck('id')->all();

    expect($visible(InventoryItem::class))->toContain($a['item']->id)->toContain($b['item']->id);
    expect($visible(PurchasedOrder::class))->toContain($a['po']->id)->toContain($b['po']->id);
    expect($visible(InventoryUnitCode::class))->toContain($a['uc']->id)->toContain($b['uc']->id);
    expect($visible(GencysSyncRun::class))->toContain($a['gsr']->id)->toContain($b['gsr']->id);
});

it('shows no inventory records to a scoped user with no team (fail-closed)', function () {
    $workspace = Workspace::factory()->create();
    $team = Team::factory()->create(['workspace_id' => $workspace->id]);
    inventoryChainForTeams($workspace, [$team], 'A-1');

    $user = scopedInventoryMember($workspace); // no team

    expect(InventoryItem::query()->visibleTo($user, $workspace)->count())->toBe(0);
    expect(InventoryTransaction::query()->visibleTo($user, $workspace)->count())->toBe(0);
    expect(PurchasedOrder::query()->visibleTo($user, $workspace)->count())->toBe(0);
    expect(InventoryUnitCode::query()->visibleTo($user, $workspace)->count())->toBe(0);
    expect(GencysSyncRun::query()->visibleTo($user, $workspace)->count())->toBe(0);
});
