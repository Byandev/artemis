<?php

use Modules\GencysERP\Models\GencysSyncRun;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryTransaction;
use Modules\Inventory\Models\PurchasedOrder;

/** Create an active inventory item in the given workspace. */
function makeInventoryItem($workspace, string $sku = 'SKU-1'): InventoryItem
{
    return InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => $sku,
        'is_active' => true,
    ]);
}

test('the transaction-history callback closes the date run it echoes back', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    makeInventoryItem($workspace, 'Airzen Anti-Lung Problems');

    $run = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_TRANSACTION_HISTORY, ['date' => '06/28/2026']);

    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', [
        'sync_run_id' => $run->id,
        'items' => [[
            'item' => 'Airzen Anti-Lung Problems',
            'transactions' => [
                ['number' => 1, 'ref_no' => 'TX-1', 'date' => '2026-06-28', 'po_qty_in' => 5, 'inventory_remaining_stock' => 5],
                ['number' => 2, 'ref_no' => 'TX-2', 'date' => '2026-06-28', 'po_qty_out' => 2, 'inventory_remaining_stock' => 3],
            ],
        ]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    $run->refresh();

    expect($run->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($run->rows_received)->toBe(2)
        ->and($run->rows_saved)->toBe(2)
        ->and($run->finished_at)->not->toBeNull();
});

test('a bare array body is read the same as a wrapped one', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    makeInventoryItem($workspace, 'Elixir of Hormuz');

    $run = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_TRANSACTION_HISTORY, ['date' => '08/30/2026']);

    // No envelope to hang a sync_run_id on, so it rides in the query string.
    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync?sync_run_id='.$run->id, [
        [
            'item' => 'Elixir of Hormuz',
            'transactions' => [
                ['number' => 1, 'item' => 'Elixir of Hormuz', 'date' => '2026-08-30', 'ref_no' => 'Rigor Esperanzate', 'rts_goods_in' => '29', 'inventory_remaining_stock' => '303'],
            ],
        ],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    expect($run->fresh()->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($run->fresh()->rows_received)->toBe(1);
});

test('items arrive by name: matched on SKU, on a transaction keyword, or created', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $bySku = makeInventoryItem($workspace, 'Amazing Kidney Care Patch');
    $byKeyword = makeInventoryItem($workspace, 'CARDIOMAX');
    $byKeyword->update(['transaction_keywords' => 'CardioMax - Heart Wellness Powder Juice, CardioMax 2.0']);

    $run = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_TRANSACTION_HISTORY, ['date' => '08/30/2026']);

    $row = fn (string $stock) => [
        ['number' => 1, 'date' => '2026-08-30', 'ref_no' => 'Anna Marie Mallo', 'po_qty_in' => '3', 'inventory_remaining_stock' => $stock],
    ];

    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', [
        'sync_run_id' => $run->id,
        'items' => [
            // Same name, different case and spacing — still the same item.
            ['item' => 'amazing  kidney care patch', 'transactions' => $row('2,403')],
            ['item' => 'CardioMax - Heart Wellness Powder Juice', 'transactions' => $row('56')],
            ['item' => 'Pikutin Habulin 2.0', 'transactions' => $row('2,595')],
        ],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    $created = InventoryItem::where('workspace_id', $workspace->id)->where('sku', 'Pikutin Habulin 2.0')->first();

    // An item we've never seen is created rather than dropped, but inactive:
    // someone still has to look at it.
    expect($created)->not->toBeNull()
        ->and($created->is_active)->toBeFalse()
        ->and(InventoryTransaction::where('inventory_item_id', $bySku->id)->count())->toBe(1)
        ->and(InventoryTransaction::where('inventory_item_id', $byKeyword->id)->count())->toBe(1)
        ->and(InventoryTransaction::where('inventory_item_id', $created->id)->count())->toBe(1)
        ->and($run->fresh()->rows_received)->toBe(3);
});

test('quantities the ERP formats with thousands separators are read as numbers', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = makeInventoryItem($workspace, 'Lunggold Repirabalm (KINTARA)');

    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', [
        'items' => [[
            'item' => 'Lunggold Repirabalm (KINTARA)',
            'transactions' => [[
                'number' => 1,
                'date' => '2026-08-30',
                'ref_no' => 'Rigor Esperanzate',
                'po_qty_in' => '0',
                'po_qty_out' => '1,200',
                'inventory_remaining_stock' => '5,047',
            ]],
        ]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    $transaction = InventoryTransaction::where('inventory_item_id', $item->id)->sole();

    expect((int) $transaction->po_qty_out)->toBe(1200)
        ->and((int) $transaction->remaining_qty)->toBe(5047)
        ->and((float) $transaction->inventory_remaining_stock)->toBe(5047.0);
});

test('a callback with no echoed id is credited to the date still in flight', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    makeInventoryItem($workspace, 'Her Reset Ovarra');

    // Only one transaction-history run is ever out at a time, so there is no
    // ambiguity about which one an un-labelled callback answers.
    $run = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_TRANSACTION_HISTORY, ['date' => '08/30/2026']);

    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', [
        'items' => [[
            'item' => 'Her Reset Ovarra',
            'transactions' => [['number' => 1, 'ref_no' => 'Rigor Esperanzate', 'date' => '2026-08-30', 'rts_goods_out' => '10', 'inventory_remaining_stock' => '479']],
        ]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    expect($run->fresh()->status)->toBe(GencysSyncRun::STATUS_SUCCESS);
});

test('a report posted in pieces keeps the run open until the last one', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    makeInventoryItem($workspace, 'PIKUTIN PERFUME BOX');

    $run = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_TRANSACTION_HISTORY, ['date' => '08/30/2026']);

    $chunk = fn (bool $more, string $ref) => $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', [
        'sync_run_id' => $run->id,
        'has_more' => $more,
        'items' => [[
            'item' => 'PIKUTIN PERFUME BOX',
            'transactions' => [['number' => 1, 'ref_no' => $ref, 'date' => '2026-08-30', 'rts_goods_in' => '12', 'inventory_remaining_stock' => '3,883']],
        ]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    $chunk(true, 'PART-1');

    expect($run->fresh()->status)->toBe(GencysSyncRun::STATUS_PENDING)
        ->and($run->fresh()->rows_received)->toBe(1);

    $chunk(false, 'PART-2');

    // The counts add up across the pieces rather than the last one overwriting.
    expect($run->fresh()->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($run->fresh()->rows_received)->toBe(2);
});

test('the older per-item callback shape still resolves its runs', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = makeInventoryItem($workspace);

    // A batch queued before the sync changed grain is still in flight.
    $run = GencysSyncRun::start($workspace->id, $item->id, GencysSyncRun::TYPE_TRANSACTION_HISTORY);

    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', [
        'items' => [[
            'id' => $item->id,
            'sync_run_id' => $run->id,
            'transactions' => [
                ['ref_no' => 'TX-1', 'date' => '2026-06-28', 'po_qty_in' => 5, 'inventory_remaining_stock' => 5],
            ],
        ]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    expect($run->fresh()->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($run->fresh()->rows_received)->toBe(1);
});

test('purchase-order callback resolves the run by the echoed sync_run_id and saves the PO', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = makeInventoryItem($workspace);

    $run = GencysSyncRun::start($workspace->id, $item->id, GencysSyncRun::TYPE_PURCHASE_ORDER);

    $this->postJson('/api/v1/public/purchase-orders/bulk-sync', [
        'data' => [[
            'id' => $item->id,
            'sync_run_id' => $run->id,
            'purchased_orders' => [[
                'control_no' => 'CN-1',
                'issue_date' => '2026-06-20',
                'total_amount' => 1000,
                'status' => 6,
                'items' => [['count' => 10, 'amount' => 100, 'total_amount' => 1000]],
                'deliveries' => [['qty' => 10, 'created_at' => '2026-06-21 10:00:00']],
            ]],
        ]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    $run->refresh();

    expect($run->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($run->rows_received)->toBe(1)
        ->and(PurchasedOrder::where('control_no', 'CN-1')->where('workspace_id', $workspace->id)->exists())->toBeTrue();
});

test('the callback resolves the exact run id echoed back, and a replay is idempotent', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = makeInventoryItem($workspace, 'Vascure Herbal Miracle Oil (46)');

    // Two runs in flight for different dates; n8n echoes the OLDER one's id.
    $target = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_TRANSACTION_HISTORY, ['date' => '06/27/2026']);
    $newer = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_TRANSACTION_HISTORY, ['date' => '06/28/2026']);

    $payload = [
        'sync_run_id' => $target->id,
        'items' => [[
            'item' => 'Vascure Herbal Miracle Oil (46)',
            'transactions' => [
                ['number' => 1, 'ref_no' => 'TX-1', 'date' => '2026-06-27', 'po_qty_in' => 1, 'inventory_remaining_stock' => 1],
            ],
        ]],
    ];

    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', $payload, ['Authorization' => 'Bearer '.$raw])->assertOk();
    // Replaying hits the same run by id — no second row, no reopened run.
    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', $payload, ['Authorization' => 'Bearer '.$raw])->assertOk();

    expect($target->fresh()->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($target->fresh()->rows_received)->toBe(1)
        ->and($newer->fresh()->status)->toBe(GencysSyncRun::STATUS_PENDING)
        ->and(InventoryTransaction::where('inventory_item_id', $item->id)->count())->toBe(1);
});

test('a success resolves earlier pending and failed runs with the same parameters', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    makeInventoryItem($workspace, 'Chia Seeds For Diabetes');

    $params = ['date' => '06/28/2026'];

    // Earlier attempts at the same date that never resolved.
    $stuck = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_TRANSACTION_HISTORY, $params);
    $failed = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_TRANSACTION_HISTORY, $params);
    $failed->fail('n8n webhook returned HTTP 500');

    // A different date — a genuine gap that must stay failed.
    $otherDate = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_TRANSACTION_HISTORY, ['date' => '06/27/2026']);
    $otherDate->fail('n8n webhook returned HTTP 500');

    $run = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_TRANSACTION_HISTORY, $params);

    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', [
        'sync_run_id' => $run->id,
        'items' => [[
            'item' => 'Chia Seeds For Diabetes',
            'transactions' => [
                ['number' => 1, 'ref_no' => 'TX-1', 'date' => '2026-06-28', 'po_qty_in' => 1, 'inventory_remaining_stock' => 1],
            ],
        ]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    expect($run->fresh()->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($stuck->fresh()->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($stuck->fresh()->rows_saved)->toBe(1)
        ->and($stuck->fresh()->message)->toContain("Resolved by sync run #{$run->id}")
        ->and($failed->fresh()->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($otherDate->fresh()->status)->toBe(GencysSyncRun::STATUS_FAILED);
});

test('the backfill command resolves runs a later success already covered', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = makeInventoryItem($workspace);

    $params = ['start_date' => '06/01/2026', 'end_date' => '06/30/2026'];

    $stuck = GencysSyncRun::start($workspace->id, $item->id, GencysSyncRun::TYPE_PURCHASE_ORDER, $params);
    $stuck->fail('No callback received within 3h (sync timed out)');

    // Later success for the same parameters — key order deliberately reversed.
    $success = GencysSyncRun::start($workspace->id, $item->id, GencysSyncRun::TYPE_PURCHASE_ORDER, array_reverse($params));
    $success->forceFill(['status' => GencysSyncRun::STATUS_SUCCESS, 'rows_received' => 4, 'rows_saved' => 4])->save();

    // Opened after the success — still outstanding, so left alone.
    $later = GencysSyncRun::start($workspace->id, $item->id, GencysSyncRun::TYPE_PURCHASE_ORDER, $params);

    $this->artisan('gencys-erp:resolve-superseded-sync-runs')->assertSuccessful();

    expect($stuck->fresh()->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($stuck->fresh()->rows_saved)->toBe(4)
        ->and($stuck->fresh()->message)->toContain("Resolved by sync run #{$success->id}")
        ->and($later->fresh()->status)->toBe(GencysSyncRun::STATUS_PENDING);
});

test('a purchase-order callback without sync_run_id leaves the run pending', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = makeInventoryItem($workspace);

    // POs still go out many items to a call, so there is no single run an
    // un-labelled callback could safely be credited to.
    $run = GencysSyncRun::start($workspace->id, $item->id, GencysSyncRun::TYPE_PURCHASE_ORDER);

    $this->postJson('/api/v1/public/purchase-orders/bulk-sync', [
        'data' => [[
            'id' => $item->id,
            'purchased_orders' => [[
                'control_no' => 'CN-9',
                'issue_date' => '2026-06-20',
                'total_amount' => 100,
                'status' => 6,
                'items' => [['count' => 1, 'amount' => 100, 'total_amount' => 100]],
            ]],
        ]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    expect($run->fresh()->status)->toBe(GencysSyncRun::STATUS_PENDING);
});

test('stale pending runs are failed by the sweeper, recent ones are left alone', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = makeInventoryItem($workspace);

    $stale = GencysSyncRun::start($workspace->id, $item->id, GencysSyncRun::TYPE_TRANSACTION_HISTORY);
    $stale->update(['started_at' => now()->subHours(5)]);

    $fresh = GencysSyncRun::start($workspace->id, $item->id, GencysSyncRun::TYPE_PURCHASE_ORDER);

    $this->artisan('gencys-erp:expire-stale-sync-runs', ['--hours' => 3])->assertSuccessful();

    expect($stale->fresh()->status)->toBe(GencysSyncRun::STATUS_FAILED)
        ->and($stale->fresh()->message)->toContain('No callback received')
        ->and($fresh->fresh()->status)->toBe(GencysSyncRun::STATUS_PENDING);
});

test('transaction-history callback stores the ERP-reported stock as-is, without recalculating', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = makeInventoryItem($workspace, 'GOLDEN MAGNESIUM SPRAY');

    $post = fn (array $rows) => $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', [
        'items' => [['item' => 'GOLDEN MAGNESIUM SPRAY', 'transactions' => $rows]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    // Initial sync: remaining_qty is taken straight from the ERP stock (50).
    $post([['number' => 1, 'ref_no' => 'TX-1', 'date' => '2026-06-28', 'po_qty_in' => 50, 'inventory_remaining_stock' => 50]]);

    $tx1 = InventoryTransaction::where('inventory_item_id', $item->id)->where('ref_no', 'TX-1')->first();
    expect((int) $tx1->remaining_qty)->toBe(50);

    // A manual correction to a row this callback doesn't mention is left alone.
    $tx1->update(['remaining_qty' => 45]);

    // Next row: the movement columns are irrelevant now — remaining_qty is simply the
    // ERP's reported stock (60). No chaining, no dependence on the prior row.
    $post([['number' => 1, 'ref_no' => 'TX-2', 'date' => '2026-06-30', 'po_qty_out' => 10, 'rts_goods_in' => 4, 'rts_bad' => 2, 'lost' => 1, 'inventory_remaining_stock' => 60]]);

    $tx2 = InventoryTransaction::where('inventory_item_id', $item->id)->where('ref_no', 'TX-2')->first();

    expect((int) $tx2->remaining_qty)->toBe(60)
        ->and((int) $tx1->fresh()->remaining_qty)->toBe(45); // corrected row untouched
});
