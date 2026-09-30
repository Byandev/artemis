<?php

use App\Models\Shop;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Inventory\Exports\InventoryItemReportExport;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryTransaction;
use Modules\Products\Models\Product;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** Give an item a ledger stock by recording its latest transaction remaining_qty. */
function stockItem(InventoryItem $item, int $remaining): void
{
    InventoryTransaction::create([
        'workspace_id' => $item->workspace_id,
        'inventory_item_id' => $item->id,
        'date' => '2026-06-01',
        'ref_no' => 'TXN-'.$item->id,
        'remaining_qty' => $remaining,
    ]);
}

test('bulkGroup creates a parent and assigns the selected items to it', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $a = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SUP-A', 'is_active' => true]);
    $b = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SUP-B', 'is_active' => true]);

    $this->actingAs($owner)
        ->post(route('workspaces.inventory.item.bulk-group', $workspace), [
            'ids' => [$a->id, $b->id],
            'new_parent_sku' => 'WIDGET-GROUP',
        ])
        ->assertRedirect();

    $parent = InventoryItem::where('workspace_id', $workspace->id)
        ->where('sku', 'WIDGET-GROUP')
        ->first();

    expect($parent)->not->toBeNull();
    expect($parent->is_parent)->toBeTrue();
    expect($a->fresh()->parent_id)->toBe($parent->id);
    expect($b->fresh()->parent_id)->toBe($parent->id);
});

test('bulkGroup with no parent info ungroups the selected items', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $parent = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'P', 'is_parent' => true, 'is_active' => true]);
    $child = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'C', 'parent_id' => $parent->id, 'is_active' => true]);

    $this->actingAs($owner)
        ->post(route('workspaces.inventory.item.bulk-group', $workspace), [
            'ids' => [$child->id],
        ])
        ->assertRedirect();

    expect($child->fresh()->parent_id)->toBeNull();
});

test('ungrouping a selected parent breaks up the whole group', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $parent = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'P', 'is_parent' => true, 'is_active' => true]);
    $childA = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'C-A', 'parent_id' => $parent->id, 'is_active' => true]);
    $childB = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'C-B', 'parent_id' => $parent->id, 'is_active' => true]);

    // The summary view lists the group under the parent's id, so that is the id
    // the Ungroup button sends — it must detach the children, not no-op.
    $this->actingAs($owner)
        ->post(route('workspaces.inventory.item.bulk-group', $workspace), [
            'ids' => [$parent->id],
        ])
        ->assertRedirect();

    expect($childA->fresh()->parent_id)->toBeNull()
        ->and($childB->fresh()->parent_id)->toBeNull()
        // The parent placeholder itself is left alone — it is reusable.
        ->and($parent->fresh())->not->toBeNull();
});

test('grouping under a parent is unaffected by the ungroup child expansion', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $parent = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'P', 'is_parent' => true, 'is_active' => true]);
    $otherParent = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'P2', 'is_parent' => true, 'is_active' => true]);
    $held = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'HELD', 'parent_id' => $otherParent->id, 'is_active' => true]);
    $loose = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'LOOSE', 'is_active' => true]);

    // Selecting a parent alongside a leaf while grouping must not drag the other
    // parent's children along — the expansion only applies to ungrouping.
    $this->actingAs($owner)
        ->post(route('workspaces.inventory.item.bulk-group', $workspace), [
            'ids' => [$loose->id, $otherParent->id],
            'parent_id' => $parent->id,
        ])
        ->assertRedirect();

    expect($loose->fresh()->parent_id)->toBe($parent->id)
        ->and($held->fresh()->parent_id)->toBe($otherParent->id);
});

test('summarize view rolls children up under the parent and sums their stock', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $parent = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'GROUP', 'is_parent' => true, 'is_active' => true]);
    $a = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SUP-A', 'parent_id' => $parent->id, 'is_active' => true]);
    $b = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SUP-B', 'parent_id' => $parent->id, 'is_active' => true]);
    stockItem($a, 30);
    stockItem($b, 12);

    $this->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspace).'?summarize=1')
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($parent) {
            $rows = collect($page->toArray()['props']['items']['data']);

            // The two child SKUs collapse into the single parent row.
            expect($rows)->toHaveCount(1);

            $row = $rows->first();
            expect((int) $row['id'])->toBe($parent->id);
            expect($row['sku'])->toBe('GROUP');
            expect((int) $row['is_group'])->toBe(1);
            expect((int) $row['child_count'])->toBe(2);
            expect((int) $row['current_stocks'])->toBe(42);
        });
});

test('the list summarizes by parent by default, with no summarize param', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $parent = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'GROUP', 'is_parent' => true, 'is_active' => true]);
    InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SUP-A', 'parent_id' => $parent->id, 'is_active' => true]);
    InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SUP-B', 'parent_id' => $parent->id, 'is_active' => true]);

    $this->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspace))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($parent) {
            $props = $page->toArray()['props'];

            expect($props['query']['summarize'])->toBeTrue();

            $rows = collect($props['items']['data']);
            expect($rows)->toHaveCount(1);
            expect((int) $rows->first()['id'])->toBe($parent->id);
            expect((int) $rows->first()['child_count'])->toBe(2);
        });
});

test('the list computes stocks needed for the lead time (3-day avg x lead time)', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $item = InventoryItem::create([
        'workspace_id' => $workspace->id, 'sku' => 'X-1', 'is_active' => true,
        'lead_time' => 5, 'three_days_average' => 4,
    ]);

    $this->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspace).'?summarize=0')
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($item) {
            $row = collect($page->toArray()['props']['items']['data'])->firstWhere('id', $item->id);
            expect((int) $row['stocks_needed_for_lead_time'])->toBe(20);
        });
});

test('editing lead time updates the item and the summarize roll-up reads it from the parent', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $parent = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'GROUP', 'is_parent' => true, 'is_active' => true, 'lead_time' => 2]);
    InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SUP-A', 'parent_id' => $parent->id, 'is_active' => true, 'lead_time' => 1, 'three_days_average' => 3]);

    // The summarize row's id is the parent — edit lead time there.
    $this->actingAs($owner)
        ->patch(route('workspaces.inventory.item.lead-time.update', ['workspace' => $workspace, 'item' => $parent->id]), ['lead_time' => 10])
        ->assertRedirect();

    expect($parent->fresh()->lead_time)->toBe(10);

    // Roll-up: group lead time = parent's 10, stocks needed = 10 × summed 3-day avg (3).
    $this->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspace).'?summarize=1')
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($parent) {
            $row = collect($page->toArray()['props']['items']['data'])->first();
            expect((int) $row['id'])->toBe($parent->id);
            expect((int) $row['lead_time'])->toBe(10);
            expect((int) $row['stocks_needed_for_lead_time'])->toBe(30);
        });
});

test('summarize view keeps the parent id/sku for a team-scoped view even though the parent has no product', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $teamA = Team::factory()->create(['workspace_id' => $workspace->id]);
    $teamB = Team::factory()->create(['workspace_id' => $workspace->id]);

    // Build a parent group whose single child's product is visible to $team.
    $groupForTeam = function (Team $team, string $parentSku, string $childSku, int $stock) use ($workspace) {
        $product = Product::factory()->create(['workspace_id' => $workspace->id]);
        $shop = Shop::factory()->forWorkspace($workspace)->create(['product_id' => $product->id]);
        $shop->teams()->attach($team->id);

        // The parent placeholder has no product of its own.
        $parent = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => $parentSku, 'is_parent' => true, 'is_active' => true]);
        $child = InventoryItem::create([
            'workspace_id' => $workspace->id, 'product_id' => $product->id,
            'sku' => $childSku, 'parent_id' => $parent->id, 'is_active' => true,
        ]);
        stockItem($child, $stock);

        return $parent;
    };

    $parentA = $groupForTeam($teamA, 'GROUP-A', 'SUP-A', 20);
    $parentB = $groupForTeam($teamB, 'GROUP-B', 'SUP-B', 99);

    // Owner "viewing as" team A -> team-scoped visibility applies. Before the fix the
    // no-product parent was filtered out and the row fell back to the child's id.
    $this->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspace)."?summarize=1&team_id={$teamA->id}")
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($parentA, $parentB) {
            $rows = collect($page->toArray()['props']['items']['data']);

            // Only team A's group is visible — team B's must not leak.
            expect($rows)->toHaveCount(1);
            expect($rows->pluck('id')->map('intval'))->not->toContain($parentB->id);

            $row = $rows->first();
            expect((int) $row['id'])->toBe($parentA->id);
            expect($row['sku'])->toBe('GROUP-A');
            expect((int) $row['is_group'])->toBe(1);
            expect((int) $row['child_count'])->toBe(1);
            expect((int) $row['current_stocks'])->toBe(20);
        });
});

test('flat view lists the individual child SKUs, tagged with their parent', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $parent = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'GROUP', 'is_parent' => true, 'is_active' => true]);
    $a = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SUP-A', 'parent_id' => $parent->id, 'is_active' => true]);

    $this->actingAs($owner)
        ->get(route('workspaces.inventory.item.index', $workspace).'?summarize=0')
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($a) {
            $rows = collect($page->toArray()['props']['items']['data']);
            $child = $rows->firstWhere('id', $a->id);

            expect($child)->not->toBeNull();
            expect($child['parent_sku'])->toBe('GROUP');
        });
});

test('the export rolls children up into one row per group', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $parent = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'GROUP', 'is_parent' => true, 'is_active' => true]);
    $a = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SUP-A', 'parent_id' => $parent->id, 'is_active' => true]);
    $b = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SUP-B', 'parent_id' => $parent->id, 'is_active' => true]);
    stockItem($a, 30);
    stockItem($b, 12);

    Excel::fake();
    Excel::matchByRegex();

    $this->actingAs($owner)
        ->get(route('workspaces.inventory.item.export', $workspace))
        ->assertOk();

    Excel::assertDownloaded('/inventory-items-.*\.xlsx/', function (InventoryItemReportExport $export) {
        $rows = iterator_to_array($export->generator());

        // One row for the whole group, with the children's stock summed.
        expect($rows)->toHaveCount(1)
            ->and($rows[0][0])->toBe('GROUP')
            ->and((int) $rows[0][10])->toBe(42);

        return true;
    });
});

test('the export stays rolled up even with the list toggled flat', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $parent = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'GROUP', 'is_parent' => true, 'is_active' => true]);
    $a = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SUP-A', 'parent_id' => $parent->id, 'is_active' => true]);
    $b = InventoryItem::create(['workspace_id' => $workspace->id, 'sku' => 'SUP-B', 'parent_id' => $parent->id, 'is_active' => true]);
    stockItem($a, 30);
    stockItem($b, 12);

    Excel::fake();
    Excel::matchByRegex();

    $this->actingAs($owner)
        ->get(route('workspaces.inventory.item.export', $workspace).'?summarize=0')
        ->assertOk();

    // The demand figures are the group's, so a per-SKU download would split one
    // reorder decision across several lines and invite double-counting it.
    Excel::assertDownloaded('/inventory-items-.*\.xlsx/', function (InventoryItemReportExport $export) {
        expect(collect(iterator_to_array($export->generator()))->pluck(0)->all())->toBe(['GROUP']);

        return true;
    });
});
