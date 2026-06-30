<?php

use Illuminate\Support\Facades\Http;
use Modules\GencysERP\Jobs\FetchInventoryItemTransactionHistory;
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

test('the fetch job fails its pending runs when the n8n handshake fails', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = makeInventoryItem($workspace);

    Http::fake(['*' => Http::response('error', 500)]);

    $run = GencysSyncRun::start($workspace->id, $item->id, GencysSyncRun::TYPE_TRANSACTION_HISTORY);

    (new FetchInventoryItemTransactionHistory(
        'https://n8n.test/webhook',
        ['workspace_id' => $workspace->id],
        [$run->id],
    ))->handle();

    expect($run->fresh()->status)->toBe(GencysSyncRun::STATUS_FAILED)
        ->and($run->fresh()->message)->toContain('HTTP 500');
});

test('transaction-history callback rolls remaining_qty forward by net movement, anchored at the audited count', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = makeInventoryItem($workspace);

    // Initial sync: first row seeds from the ERP stock (no audit yet).
    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', [
        'items' => [[
            'id' => $item->id,
            'transactions' => [
                ['ref_no' => 'TX-1', 'date' => '2026-06-28', 'po_qty_in' => 50, 'inventory_remaining_stock' => 50],
            ],
        ]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    // Monthly physical count: actual is 45 (5 short). This becomes the starting balance.
    $tx1 = InventoryTransaction::where('inventory_item_id', $item->id)->where('ref_no', 'TX-1')->first();
    $tx1->update(['remaining_qty' => 45, 'is_audited' => true]);

    // Next row: out 10, returned-to-stock 4, bad 2, lost 1 → net −9. The bogus ERP stock
    // (999) must be ignored; balance = 45 − 9 = 36.
    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', [
        'items' => [[
            'id' => $item->id,
            'transactions' => [
                ['ref_no' => 'TX-2', 'date' => '2026-06-30', 'po_qty_out' => 10, 'rts_goods_in' => 4, 'rts_bad' => 2, 'lost' => 1, 'inventory_remaining_stock' => 999],
            ],
        ]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    $tx2 = InventoryTransaction::where('inventory_item_id', $item->id)->where('ref_no', 'TX-2')->first();

    expect((int) $tx2->remaining_qty)->toBe(36)
        ->and((int) $item->fresh()->remaining_qty)->toBe(36)
        ->and((int) $tx1->fresh()->remaining_qty)->toBe(45);
});

test('recalculateActualStock re-levels the whole ledger from the audited anchor by net movement', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = makeInventoryItem($workspace);

    // A small ledger: seed 100, sell 10, receive 5.
    $t1 = InventoryTransaction::create(['workspace_id' => $workspace->id, 'inventory_item_id' => $item->id, 'date' => '2026-06-01', 'ref_no' => 'A', 'po_qty_in' => 100, 'inventory_remaining_stock' => 100]);
    $t2 = InventoryTransaction::create(['workspace_id' => $workspace->id, 'inventory_item_id' => $item->id, 'date' => '2026-06-02', 'ref_no' => 'B', 'po_qty_out' => 10]);
    $t3 = InventoryTransaction::create(['workspace_id' => $workspace->id, 'inventory_item_id' => $item->id, 'date' => '2026-06-03', 'ref_no' => 'C', 'po_qty_in' => 5]);

    // Audit at t2: counted 80 (the new starting balance).
    $t2->update(['remaining_qty' => 80, 'is_audited' => true]);

    $item->recalculateActualStock();

    // t1 seeds from ERP stock (100); t2 is the anchor (80); t3 = 80 + 5 = 85.
    expect((int) $t1->fresh()->remaining_qty)->toBe(100)
        ->and((int) $t2->fresh()->remaining_qty)->toBe(80)
        ->and((int) $t3->fresh()->remaining_qty)->toBe(85)
        ->and((int) $item->fresh()->remaining_qty)->toBe(85);
});
