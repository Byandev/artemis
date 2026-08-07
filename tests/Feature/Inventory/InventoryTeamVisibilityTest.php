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
use Modules\Inventory\Support\InventoryStockColumns;

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

test('the joined stock columns neither leak across teams nor duplicate rows', function () {
    $workspace = Workspace::factory()->create();
    $mine = Team::factory()->create(['workspace_id' => $workspace->id]);
    $theirs = Team::factory()->create(['workspace_id' => $workspace->id]);

    ['item' => $mineItem] = inventoryChainForTeams($workspace, [$mine], 'TEAM-MINE');
    ['item' => $theirItem] = inventoryChainForTeams($workspace, [$theirs], 'TEAM-THEIRS');

    // Several transactions and deliveries on the visible item. Each derived
    // table must still contribute exactly one row — otherwise the joins would
    // multiply the item out and inflate every summed column downstream.
    foreach (['2026-07-02', '2026-07-03', '2026-07-04'] as $i => $date) {
        InventoryTransaction::create([
            'workspace_id' => $workspace->id, 'inventory_item_id' => $mineItem->id,
            'date' => $date, 'ref_no' => "REF-EXTRA-{$i}", 'remaining_qty' => 100 + $i,
        ]);
    }

    $released = PurchasedOrder::create(['workspace_id' => $workspace->id, 'issue_date' => '2026-07-05', 'status' => 6]);
    $line = PurchasedOrderItem::create([
        'inventory_purchased_order_id' => $released->id, 'inventory_item_id' => $mineItem->id, 'count' => 50,
    ]);
    $line->deliveries()->create(['delivery_date' => '2026-07-06', 'qty' => 10]);
    $line->deliveries()->create(['delivery_date' => '2026-07-07', 'qty' => 15]);

    $user = scopedInventoryMember($workspace, [$mine]);

    $rows = InventoryItem::query()
        ->where('inventory_items.workspace_id', $workspace->id)
        ->visibleTo($user, $workspace)
        ->tap(fn ($q) => InventoryStockColumns::applyJoins($q))
        ->selectRaw('inventory_items.id, inventory_items.sku')
        ->selectRaw(InventoryStockColumns::currentStocks().' as current_stocks')
        ->selectRaw(InventoryStockColumns::waitingStocks().' as waiting_stocks')
        ->selectRaw(InventoryStockColumns::requestedStocks().' as requested_stocks')
        ->get();

    // Scoping still holds with three joins in play.
    expect($rows->pluck('sku')->all())->toBe(['TEAM-MINE']);

    // One row, despite four transactions and two deliveries on this item.
    expect($rows)->toHaveCount(1);

    $row = $rows->first();

    // The latest transaction wins (highest date, then id) — not a sum of all four.
    expect((int) $row->current_stocks)->toBe(102);
    // 25 still owed on the released order, plus the 5 on the status-1 order
    // the chain helper raised — both are committed quantity.
    expect((int) $row->waiting_stocks)->toBe(30);
    // Of that, the 5 nobody has sent to a supplier yet.
    expect((int) $row->requested_stocks)->toBe(5);

    // The other team's item is untouched by any of it.
    $ownerRows = InventoryItem::query()
        ->where('inventory_items.workspace_id', $workspace->id)
        ->tap(fn ($q) => InventoryStockColumns::applyJoins($q))
        ->selectRaw('inventory_items.id, inventory_items.sku')
        ->selectRaw(InventoryStockColumns::waitingStocks().' as waiting_stocks')
        ->get()->keyBy('sku');

    expect($ownerRows)->toHaveCount(2);
    // The other team's chain has its own status-1 order for 5 units.
    expect($ownerRows['TEAM-THEIRS']->waiting_stocks)->toEqual(5);
    expect($ownerRows['TEAM-MINE']->waiting_stocks)->toEqual(30);
});
