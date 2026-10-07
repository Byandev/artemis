<?php

use App\Models\Order;
use App\Models\Page;
use App\Models\Shop;
use App\Models\Workspace;

function orderPageProps(Workspace $workspace, array $filter = []): array
{
    return test()->get(route('workspaces.pancake.orders.index', [
        'workspace' => $workspace,
        'filter' => $filter,
    ]))->assertOk()->viewData('page')['props'];
}

it('narrows the list and the tab counts to one page', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $pageA = Page::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Alpha']);
    $pageB = Page::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Beta']);

    Order::factory()->forPage($pageA)->count(2)->create();
    Order::factory()->forPage($pageB)->create();

    $props = orderPageProps($workspace, ['page' => $pageA->id]);

    expect($props['orders']['data'])->toHaveCount(2)
        ->and(collect($props['orders']['data'])->pluck('page_id')->unique()->all())->toBe([$pageA->id])
        ->and($props['totalCount'])->toBe(2);

    expect(orderPageProps($workspace)['orders']['data'])->toHaveCount(3);
});

it('takes several pages at once', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    [$a, $b, $c] = Page::factory()->count(3)->create(['workspace_id' => $workspace->id]);

    Order::factory()->forPage($a)->create();
    Order::factory()->forPage($b)->create();
    Order::factory()->forPage($c)->create();

    $rows = orderPageProps($workspace, ['page' => "{$a->id},{$b->id}"])['orders']['data'];

    expect(collect($rows)->pluck('page_id')->sort()->values()->all())->toBe([$a->id, $b->id]);
});

it('offers only the workspace\'s own pages', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $mine = Page::factory()->create(['workspace_id' => $workspace->id]);
    Page::factory()->create(['workspace_id' => $other->id]);

    expect(collect(orderPageProps($workspace)['pages'])->pluck('id')->all())->toBe([$mine->id]);
});

it('narrows the list to one or more shops', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    [$a, $b, $c] = Shop::factory()->count(3)->create(['workspace_id' => $workspace->id]);

    foreach ([$a, $b, $c] as $shop) {
        Order::factory()->forPage(Page::factory()->create(['workspace_id' => $workspace->id, 'shop_id' => $shop->id]))->create();
    }

    $one = orderPageProps($workspace, ['shop' => $a->id]);

    expect($one['orders']['data'])->toHaveCount(1)
        ->and($one['orders']['data'][0]['shop_id'])->toBe($a->id)
        ->and($one['totalCount'])->toBe(1);

    $rows = orderPageProps($workspace, ['shop' => "{$a->id},{$b->id}"])['orders']['data'];

    expect(collect($rows)->pluck('shop_id')->sort()->values()->all())->toBe([$a->id, $b->id]);
});

it('offers only the workspace\'s own shops', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();
    $this->actingAs($owner);

    $mine = Shop::factory()->create(['workspace_id' => $workspace->id]);
    Shop::factory()->create(['workspace_id' => $other->id]);

    expect(collect(orderPageProps($workspace)['shops'])->pluck('id')->all())->toBe([$mine->id]);
});
