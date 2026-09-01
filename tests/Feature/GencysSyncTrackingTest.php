<?php

use Modules\GencysERP\Models\GencysSyncRun;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryTransaction;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Models\PurchasedOrderItem;

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

test('an un-labelled purchase-order callback is credited to its own type, not another', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    makeInventoryItem($workspace, 'Her Reset Ovarra');

    // A transaction-history run is also in flight; a PO callback must not close it.
    $transactions = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_TRANSACTION_HISTORY, ['date' => '08/30/2026']);
    $orders = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_PURCHASE_ORDER, ['start_date' => '06/01/2026', 'end_date' => '08/30/2026']);

    $this->postJson('/api/v1/public/purchase-orders/bulk-sync', [
        [
            'control_no' => 'CN-TP983',
            'issue_date' => '2026-08-12',
            'total_amount' => 18000,
            'status' => 6,
            'supplier' => 'BFM',
            'items' => [['count' => 400, 'amount' => 45, 'total_amount' => 18000, 'item' => 'Her Reset Ovarra']],
            'statusLogs' => [],
            'deliveries' => [],
        ],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    expect($orders->fresh()->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($transactions->fresh()->status)->toBe(GencysSyncRun::STATUS_PENDING);
});

test('the purchase-order callback saves the range the run carried, by item name', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $known = makeInventoryItem($workspace, 'Anti-Diabetes Herbal Foot Patch');

    $run = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_PURCHASE_ORDER, ['start_date' => '06/01/2026', 'end_date' => '08/30/2026']);

    $this->postJson('/api/v1/public/purchase-orders/bulk-sync', [[
        'sync_run_id' => $run->id,
        'purchased_orders' => [
            [
                'issue_date' => '2026-08-26',
                'delivery_no' => 'DN-TP1004',
                'cust_po_no' => 'CPO-TP1004',
                'control_no' => 'CN-TP1004',
                'delivery_fee' => 1500,
                'total_amount' => 27900,
                'status' => 6,
                'created_at' => '2026-08-26 13:24:01',
                'supplier' => 'Kintara Manuf Ventures Inc',
                'items' => [['count' => 2400, 'amount' => 11, 'total_amount' => 26400, 'item' => 'Anti-Diabetes Herbal Foot Patch']],
                'statusLogs' => [
                    ['status' => 'Approve', 'by' => 'GENCYS - Angelyn Macabasag', 'detail' => 'approved', 'timestamp' => '2026-08-26 14:12:19'],
                    ['status' => 'To Pay', 'by' => 'GENCYS - Angelyn Macabasag', 'detail' => 'to pay', 'timestamp' => '2026-08-26 14:12:29'],
                    ['status' => 'Paid', 'by' => 'RENZ LAICA MERCADO', 'detail' => 'PAID-FULL', 'timestamp' => '2026-08-26 16:40:40'],
                    ['status' => 'For Purchase', 'by' => 'GENCYS - Angelyn Macabasag', 'detail' => 'for purchased', 'timestamp' => '2026-08-27 10:11:39'],
                    ['status' => 'Purchased', 'by' => 'GENCYS - Angelyn Macabasag', 'detail' => 'purchased', 'timestamp' => '2026-08-27 10:11:49'],
                ],
                'deliveries' => [['qty' => 2398, 'created_at' => '2026-08-29 18:48:11']],
            ],
            [
                'issue_date' => '2026-08-15',
                'control_no' => 'CN-TP984',
                'delivery_fee' => 0,
                'total_amount' => 600250,
                'status' => 6,
                'supplier' => 'ALL',
                // An item we have never seen — created rather than dropped.
                'items' => [['count' => 4900, 'amount' => 122.5, 'total_amount' => 600250, 'item' => 'Beyou Acai Berry Glow']],
                'statusLogs' => [],
                'deliveries' => [],
            ],
        ],
    ]], ['Authorization' => 'Bearer '.$raw])->assertOk();

    $order = PurchasedOrder::where('control_no', 'CN-TP1004')->sole();
    $line = $order->items()->sole();

    expect($order->cust_po_no)->toBe('CPO-TP1004')
        ->and($order->supplier)->toBe('Kintara Manuf Ventures Inc')
        ->and((int) $order->status)->toBe(6)
        // Two weeks after issue, since the ERP sends no expected date.
        ->and($order->expected_delivery_date->toDateString())->toBe('2026-09-09')
        ->and($line->inventory_item_id)->toBe($known->id)
        ->and((int) $line->count)->toBe(2400)
        ->and((int) $line->deliveries()->sum('qty'))->toBe(2398)
        ->and($order->statusLogs()->count())->toBe(5);

    // The stage stamps are no longer sent, so they come off the trail.
    expect($order->approved_at->toDateTimeString())->toBe('2026-08-26 14:12:19')
        ->and($order->to_pay_at->toDateTimeString())->toBe('2026-08-26 14:12:29')
        ->and($order->paid_at->toDateTimeString())->toBe('2026-08-26 16:40:40')
        ->and($order->for_purchase_at->toDateTimeString())->toBe('2026-08-27 10:11:39')
        ->and($order->purchased_at->toDateTimeString())->toBe('2026-08-27 10:11:49');

    $created = InventoryItem::where('workspace_id', $workspace->id)->where('sku', 'Beyou Acai Berry Glow')->first();

    expect($created)->not->toBeNull()
        ->and($created->is_active)->toBeFalse()
        ->and(PurchasedOrder::where('workspace_id', $workspace->id)->count())->toBe(2)
        ->and($run->fresh()->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($run->fresh()->rows_received)->toBe(2);
});

test('the run id may wrap the orders as an object rather than a list', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $patch = makeInventoryItem($workspace, 'Amazing Kidney Care Patch');

    $run = GencysSyncRun::start($workspace->id, null, GencysSyncRun::TYPE_PURCHASE_ORDER, ['start_date' => '06/01/2026', 'end_date' => '08/30/2026']);

    // The shape n8n posts: one object, the run id beside the orders.
    $this->postJson('/api/v1/public/purchase-orders/bulk-sync', [
        'sync_run_id' => $run->id,
        'purchased_orders' => [
            [
                'issue_date' => '2026-08-29',
                'delivery_no' => 'DN-TP1013',
                'cust_po_no' => 'CPO-TP1013',
                'control_no' => 'CN-TP1013',
                'delivery_fee' => 1000,
                'total_amount' => 29800,
                'status' => 3,
                'created_at' => '2026-08-29 15:57:42',
                'supplier' => 'Kintara Manuf Ventures Inc',
                'items' => [['count' => 1200, 'amount' => 24, 'total_amount' => 28800, 'item' => 'Amazing Kidney Care Patch']],
                'statusLogs' => [
                    ['status' => 'Approve', 'by' => 'GENCYS - Angelyn Macabasag', 'detail' => 'approved', 'timestamp' => '2026-08-29 16:20:46'],
                    ['status' => 'To Pay', 'by' => 'GENCYS - Angelyn Macabasag', 'detail' => 'to pay', 'timestamp' => '2026-08-29 16:20:54'],
                ],
                'deliveries' => [],
            ],
            [
                'issue_date' => '2026-08-08',
                'control_no' => 'CN-TP971',
                'delivery_fee' => 3000,
                'total_amount' => 503000,
                'status' => 6,
                'supplier' => 'Kintara Manuf Ventures Inc',
                'items' => [['count' => 12500, 'amount' => 40, 'total_amount' => 500000, 'item' => 'Lunggold Repirabalm (KINTARA)']],
                // A Draft entry sits ahead of Approve and belongs to no stage.
                'statusLogs' => [
                    ['status' => 'Draft', 'by' => 'MARIO REDENTOR MOSLARES', 'detail' => 'APPROVED', 'timestamp' => '2026-08-10 08:19:08'],
                    ['status' => 'Approve', 'by' => 'MARIO REDENTOR MOSLARES', 'detail' => 'APPROVED', 'timestamp' => '2026-08-10 08:22:26'],
                ],
                'deliveries' => [
                    ['qty' => 161, 'created_at' => '2026-08-16 17:17:25'],
                    ['qty' => 1235, 'created_at' => '2026-08-18 11:14:04'],
                ],
            ],
        ],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    $first = PurchasedOrder::where('control_no', 'CN-TP1013')->sole();
    $second = PurchasedOrder::where('control_no', 'CN-TP971')->sole();

    expect($first->items()->sole()->inventory_item_id)->toBe($patch->id)
        ->and($first->approved_at->toDateTimeString())->toBe('2026-08-29 16:20:46')
        ->and($first->paid_at)->toBeNull()
        ->and($second->approved_at->toDateTimeString())->toBe('2026-08-10 08:22:26')
        ->and((int) $second->items()->sole()->deliveries()->sum('qty'))->toBe(1396)
        ->and($run->fresh()->status)->toBe(GencysSyncRun::STATUS_SUCCESS)
        ->and($run->fresh()->rows_received)->toBe(2)
        ->and($run->fresh()->rows_saved)->toBe(2);
});

test('an order keeps only the lines the ERP still reports', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $before = makeInventoryItem($workspace, 'HIKARI PARAGIS THERAPY HEART CARE');
    $after = makeInventoryItem($workspace, 'HIKARI PARAGIS THERAPY HEART CARE (Satellite)');

    $post = fn (string $item) => $this->postJson('/api/v1/public/purchase-orders/bulk-sync', [
        [
            'control_no' => 'CN-TP1010',
            'issue_date' => '2026-08-28',
            'total_amount' => 32750,
            'status' => 6,
            'items' => [['count' => 800, 'amount' => 40, 'total_amount' => 32000, 'item' => $item]],
            'deliveries' => [],
        ],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    $post('HIKARI PARAGIS THERAPY HEART CARE');
    // The ERP's spelling changes and the line resolves elsewhere. Left to
    // accumulate, the order would owe 800 twice over.
    $post('HIKARI PARAGIS THERAPY HEART CARE (Satellite)');

    $order = PurchasedOrder::where('control_no', 'CN-TP1010')->sole();

    expect($order->items()->count())->toBe(1)
        ->and($order->items()->sole()->inventory_item_id)->toBe($after->id)
        ->and(PurchasedOrderItem::where('inventory_item_id', $before->id)->count())->toBe(0);
});

test('a delivery row with no quantity is a settlement note, not a receipt', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    makeInventoryItem($workspace, 'Amazing Life Super Recovery Gel (KINTARA)');

    $this->postJson('/api/v1/public/purchase-orders/bulk-sync', [
        [
            'control_no' => 'CN-TP902',
            'issue_date' => '2026-07-22',
            'total_amount' => 13450,
            'status' => 7,
            'items' => [['count' => 300, 'amount' => 44, 'total_amount' => 13200, 'item' => 'Amazing Life Super Recovery Gel (KINTARA)']],
            'deliveries' => [
                ['qty' => 300, 'created_at' => '2026-07-29 09:26:24'],
                ['qty' => null, 'created_at' => '2026-08-06 19:46:05'],
            ],
        ],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    $line = PurchasedOrder::where('control_no', 'CN-TP902')->sole()->items()->sole();

    expect($line->deliveries()->count())->toBe(1)
        ->and((int) $line->deliveries()->sum('qty'))->toBe(300);
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
