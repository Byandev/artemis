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

test('transaction-history callback resolves the run by the echoed sync_run_id', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = makeInventoryItem($workspace);

    $run = GencysSyncRun::start($workspace->id, $item->id, GencysSyncRun::TYPE_TRANSACTION_HISTORY);

    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', [
        'items' => [[
            'id' => $item->id,
            'sync_run_id' => $run->id,
            'transactions' => [
                ['ref_no' => 'TX-1', 'date' => '2026-06-28', 'po_qty_in' => 5, 'inventory_remaining_stock' => 5],
                ['ref_no' => 'TX-2', 'date' => '2026-06-28', 'po_qty_out' => 2, 'inventory_remaining_stock' => 3],
            ],
        ]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    $run->refresh();

    expect($run->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($run->rows_received)->toBe(2)
        ->and($run->rows_saved)->toBe(2)
        ->and($run->finished_at)->not->toBeNull();
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
    $item = makeInventoryItem($workspace);

    // Two pending runs for the same item; n8n echoes the OLDER one's id.
    $target = GencysSyncRun::start($workspace->id, $item->id, GencysSyncRun::TYPE_TRANSACTION_HISTORY);
    $newer = GencysSyncRun::start($workspace->id, $item->id, GencysSyncRun::TYPE_TRANSACTION_HISTORY);

    $payload = [
        'items' => [[
            'id' => $item->id,
            'sync_run_id' => $target->id,
            'transactions' => [
                ['ref_no' => 'TX-1', 'date' => '2026-06-28', 'po_qty_in' => 1, 'inventory_remaining_stock' => 1],
            ],
        ]],
    ];

    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', $payload, ['Authorization' => 'Bearer '.$raw])->assertOk();
    // Replaying hits the same run by id — no duplicate row.
    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', $payload, ['Authorization' => 'Bearer '.$raw])->assertOk();

    expect($target->fresh()->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($newer->fresh()->status)->toBe(GencysSyncRun::STATUS_PENDING)
        ->and(GencysSyncRun::where('inventory_item_id', $item->id)->count())->toBe(2);
});

test('a success resolves earlier pending and failed runs with the same parameters', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = makeInventoryItem($workspace);
    $other = makeInventoryItem($workspace, 'SKU-2');

    $params = ['date' => '06/28/2026'];

    // Earlier attempts at the same item/date that never resolved.
    $stuck = GencysSyncRun::start($workspace->id, $item->id, GencysSyncRun::TYPE_TRANSACTION_HISTORY, $params);
    $failed = GencysSyncRun::start($workspace->id, $item->id, GencysSyncRun::TYPE_TRANSACTION_HISTORY, $params);
    $failed->fail('n8n webhook returned HTTP 500');

    // Same item, different date — a genuine gap that must stay failed.
    $otherDate = GencysSyncRun::start($workspace->id, $item->id, GencysSyncRun::TYPE_TRANSACTION_HISTORY, ['date' => '06/27/2026']);
    $otherDate->fail('n8n webhook returned HTTP 500');

    // Same date, different item — likewise untouched.
    $otherItem = GencysSyncRun::start($workspace->id, $other->id, GencysSyncRun::TYPE_TRANSACTION_HISTORY, $params);

    $run = GencysSyncRun::start($workspace->id, $item->id, GencysSyncRun::TYPE_TRANSACTION_HISTORY, $params);

    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', [
        'items' => [[
            'id' => $item->id,
            'sync_run_id' => $run->id,
            'transactions' => [
                ['ref_no' => 'TX-1', 'date' => '2026-06-28', 'po_qty_in' => 1, 'inventory_remaining_stock' => 1],
            ],
        ]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    expect($run->fresh()->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($stuck->fresh()->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($stuck->fresh()->rows_saved)->toBe(1)
        ->and($stuck->fresh()->message)->toContain("Resolved by sync run #{$run->id}")
        ->and($failed->fresh()->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($otherDate->fresh()->status)->toBe(GencysSyncRun::STATUS_FAILED)
        ->and($otherItem->fresh()->status)->toBe(GencysSyncRun::STATUS_PENDING);
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

test('a callback without sync_run_id leaves the run pending', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = makeInventoryItem($workspace);

    $run = GencysSyncRun::start($workspace->id, $item->id, GencysSyncRun::TYPE_TRANSACTION_HISTORY);

    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', [
        'items' => [[
            'id' => $item->id,
            'transactions' => [
                ['ref_no' => 'TX-1', 'date' => '2026-06-28', 'po_qty_in' => 1, 'inventory_remaining_stock' => 1],
            ],
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
    $item = makeInventoryItem($workspace);

    // Initial sync: remaining_qty is taken straight from the ERP stock (50).
    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', [
        'items' => [[
            'id' => $item->id,
            'transactions' => [
                ['ref_no' => 'TX-1', 'date' => '2026-06-28', 'po_qty_in' => 50, 'inventory_remaining_stock' => 50],
            ],
        ]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    $tx1 = InventoryTransaction::where('inventory_item_id', $item->id)->where('ref_no', 'TX-1')->first();
    expect((int) $tx1->remaining_qty)->toBe(50);

    // A manual correction to an existing row is preserved (firstOrCreate never rewrites it).
    $tx1->update(['remaining_qty' => 45]);

    // Next row: the movement columns are irrelevant now — remaining_qty is simply the
    // ERP's reported stock (60). No chaining, no dependence on the prior row.
    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', [
        'items' => [[
            'id' => $item->id,
            'transactions' => [
                ['ref_no' => 'TX-2', 'date' => '2026-06-30', 'po_qty_out' => 10, 'rts_goods_in' => 4, 'rts_bad' => 2, 'lost' => 1, 'inventory_remaining_stock' => 60],
            ],
        ]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    $tx2 = InventoryTransaction::where('inventory_item_id', $item->id)->where('ref_no', 'TX-2')->first();

    expect((int) $tx2->remaining_qty)->toBe(60)
        ->and((int) $tx1->fresh()->remaining_qty)->toBe(45); // corrected row untouched
});
