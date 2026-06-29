<?php

use Illuminate\Support\Facades\Http;
use Modules\GencysERP\Jobs\FetchInventoryItemTransactionHistory;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\Inventory\Models\InventoryItem;

/** Create an active inventory item in the given workspace. */
function makeInventoryItem($workspace, string $sku = 'SKU-1'): InventoryItem
{
    return InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => $sku,
        'is_active' => true,
    ]);
}

test('transaction-history callback resolves the item pending run to success', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = makeInventoryItem($workspace);

    $run = GencysSyncRun::start($workspace->id, $item->id, GencysSyncRun::TYPE_TRANSACTION_HISTORY);

    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', [
        'items' => [[
            'id' => $item->id,
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

test('purchase-order callback resolves the item pending run to success', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = makeInventoryItem($workspace);

    $run = GencysSyncRun::start($workspace->id, $item->id, GencysSyncRun::TYPE_PURCHASE_ORDER);

    $this->postJson('/api/v1/public/purchase-orders/bulk-sync', [
        [
            'control_no' => 'CN-1',
            'issue_date' => '2026-06-20',
            'total_amount' => 1000,
            'status' => 6,
            'items' => [
                ['inventory_item_id' => $item->id, 'count' => 10, 'amount' => 100, 'total_amount' => 1000],
            ],
            'deliveries' => [],
        ],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    $run->refresh();

    expect($run->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($run->rows_received)->toBe(1);
});

test('the callback resolves the exact run id echoed back, not just the latest pending', function () {
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

    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', $payload, [
        'Authorization' => 'Bearer '.$raw,
    ])->assertOk();

    expect($target->fresh()->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($newer->fresh()->status)->toBe(GencysSyncRun::STATUS_PENDING);

    // A replayed callback hits the same run by id — no duplicate row is created.
    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', $payload, [
        'Authorization' => 'Bearer '.$raw,
    ])->assertOk();

    expect(GencysSyncRun::where('inventory_item_id', $item->id)->count())->toBe(2);
});

test('a callback with no outstanding run still records a successful run', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = makeInventoryItem($workspace);

    // No pending run created beforehand (e.g. a manual/replayed n8n call).
    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', [
        'items' => [[
            'id' => $item->id,
            'transactions' => [
                ['ref_no' => 'TX-1', 'date' => '2026-06-28', 'po_qty_in' => 5, 'inventory_remaining_stock' => 5],
            ],
        ]],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    $run = GencysSyncRun::where('inventory_item_id', $item->id)->first();

    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($run->rows_received)->toBe(1);
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
