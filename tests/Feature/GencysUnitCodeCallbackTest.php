<?php

use Modules\Inventory\Models\InventoryUnitCode;
use Modules\Inventory\Models\InventoryUnitCodeItem;

test('the unit-codes callback upserts a flat list into the generic tables, authed by header', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $payload = [
        [
            'row_id' => '421',
            'sku' => 'BD0046-P4PPFA10MP',
            'unit_code' => 'Pikutin 4pcs Padrino Free Atomizer + 10ml Perfume',
            'total_amount' => '3,552.00',
            'items' => [
                ['sku' => 'Pikutin Padrino 1.0', 'quantity' => 4],
                ['sku' => 'Pikutin Travel Atomizer', 'quantity' => 1],
                ['sku' => 'Pikutin 10ml RANDOM', 'quantity' => 1],
            ],
        ],
        [
            'row_id' => '410',
            'sku' => 'BD0046-PCFM',
            'unit_code' => 'Pikutin Comb For Men',
            'total_amount' => '0.00',
            'items' => [
                ['sku' => 'Pikutin Comb For Men', 'quantity' => 1],
            ],
        ],
    ];

    $this->postJson('/api/v1/public/inventory/unit-codes/bulk-sync', $payload, [
        'Authorization' => 'Bearer '.$raw,
    ])->assertOk()->assertJson(['created' => 2, 'updated' => 0, 'skipped' => 0]);

    $uc = InventoryUnitCode::where('workspace_id', $workspace->id)
        ->where('unit_code', 'Pikutin 4pcs Padrino Free Atomizer + 10ml Perfume')
        ->first();

    expect($uc)->not->toBeNull()
        ->and($uc->sku)->toBe('BD0046-P4PPFA10MP')
        // "3,552.00" must not be truncated to 3.0 by the float cast.
        ->and((float) $uc->total_amount)->toBe(3552.0)
        ->and($uc->items)->toHaveCount(3);

    $item = InventoryUnitCodeItem::where('workspace_id', $workspace->id)
        ->where('unit_code', $uc->unit_code)
        ->where('item_code', 'Pikutin Padrino 1.0')
        ->first();

    expect($item)->not->toBeNull()->and($item->quantity)->toBe(4);
});

test('re-syncing the same unit_code updates it and replaces its items', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $first = [[
        'unit_code' => 'Bundle Set A',
        'sku' => 'OLD-SKU',
        'total_amount' => '100.00',
        'items' => [
            ['sku' => 'Item A', 'quantity' => 1],
            ['sku' => 'Item B', 'quantity' => 2],
        ],
    ]];

    $this->postJson('/api/v1/public/inventory/unit-codes/bulk-sync', $first, ['Authorization' => 'Bearer '.$raw])->assertOk();

    $second = [[
        'unit_code' => 'Bundle Set A',
        'sku' => 'NEW-SKU',
        'total_amount' => '250.00',
        'items' => [
            ['sku' => 'Item C', 'quantity' => 5],
        ],
    ]];

    $this->postJson('/api/v1/public/inventory/unit-codes/bulk-sync', $second, ['Authorization' => 'Bearer '.$raw])
        ->assertOk()->assertJson(['created' => 0, 'updated' => 1]);

    $uc = InventoryUnitCode::where('workspace_id', $workspace->id)
        ->where('unit_code', 'Bundle Set A')
        ->first();

    expect(InventoryUnitCode::where('workspace_id', $workspace->id)->count())->toBe(1)
        ->and($uc->sku)->toBe('NEW-SKU')
        ->and((float) $uc->total_amount)->toBe(250.0)
        ->and($uc->items)->toHaveCount(1)
        ->and($uc->items->first()->item_code)->toBe('Item C')
        ->and($uc->items->first()->quantity)->toBe(5);
});

test('the unit-codes callback rejects requests without a valid api key', function () {
    $this->postJson('/api/v1/public/inventory/unit-codes/bulk-sync', [
        ['unit_code' => 'X', 'items' => []],
    ])->assertUnauthorized();
});
