<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Models\PurchasedOrderStatusLog;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** An active inventory item for the ERP's PO to hang off. */
function poSyncItem($workspace): InventoryItem
{
    return InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-SYNC',
        'is_active' => true,
    ]);
}

/** Post one PO for one item through the public sync endpoint. */
function syncOrder($workspace, string $rawKey, InventoryItem $item, array $po): void
{
    test()->postJson('/api/v1/public/purchase-orders/bulk-sync', [
        'data' => [[
            'id' => $item->id,
            'sync_run_id' => null,
            'purchased_orders' => [$po],
        ]],
    ], ['Authorization' => 'Bearer '.$rawKey])->assertOk();
}

/** The shape the ERP now sends, trimmed to what these tests care about. */
function erpOrder(array $overrides = []): array
{
    return array_merge([
        'control_no' => 'CN-TP910',
        'issue_date' => '2026-07-23',
        'delivery_no' => 'DN-TP910',
        'cust_po_no' => 'CPO-TP910',
        'delivery_fee' => 750,
        'total_amount' => 10890,
        'status' => 6,
        'supplier' => 'Kintara Manuf Ventures Inc',
        'items' => [['count' => 400, 'amount' => 25.35, 'total_amount' => 10140]],
        'deliveries' => [['qty' => 399, 'created_at' => '2026-08-01 12:00:33']],
        'statusLogs' => [
            ['status' => 'Approve', 'by' => 'GENCYS - Angelyn Macabasag', 'detail' => 'approve', 'timestamp' => '2026-07-23 17:32:32'],
            ['status' => 'To Pay', 'by' => 'GENCYS - Angelyn Macabasag', 'detail' => 'to pay', 'timestamp' => '2026-07-23 17:33:52'],
            ['status' => 'Paid', 'by' => 'RENZ LAICA MERCADO', 'detail' => 'PAID', 'timestamp' => '2026-07-24 16:23:30'],
            ['status' => 'For Purchase', 'by' => 'GENCYS - Angelyn Macabasag', 'detail' => 'for purchased', 'timestamp' => '2026-07-24 16:52:25'],
        ],
    ], $overrides);
}

test('the sync records the supplier and the whole status trail', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = poSyncItem($workspace);

    syncOrder($workspace, $raw, $item, erpOrder());

    $order = PurchasedOrder::where('control_no', 'CN-TP910')->firstOrFail();

    expect($order->supplier)->toBe('Kintara Manuf Ventures Inc')
        ->and($order->statusLogs)->toHaveCount(4);

    // Oldest first, and the ERP's own labels kept verbatim.
    expect($order->statusLogs->pluck('status')->all())
        ->toBe(['Approve', 'To Pay', 'Paid', 'For Purchase']);

    expect($order->statusLogs->first()->by)->toBe('GENCYS - Angelyn Macabasag')
        ->and($order->statusLogs->first()->logged_at->toDateTimeString())->toBe('2026-07-23 17:32:32');
});

test('paid_at is taken from the Paid entry in the trail', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = poSyncItem($workspace);

    syncOrder($workspace, $raw, $item, erpOrder());

    $order = PurchasedOrder::where('control_no', 'CN-TP910')->firstOrFail();

    // The Paid log's timestamp, not the issue date and not the approval.
    expect($order->paid_at->toDateTimeString())->toBe('2026-07-24 16:23:30');
});

test('an order with no Paid entry yet has no paid date', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = poSyncItem($workspace);

    // Approved and queued for payment, but not paid — the real "status": 3 case.
    syncOrder($workspace, $raw, $item, erpOrder([
        'status' => 3,
        'statusLogs' => [
            ['status' => 'Approve', 'by' => 'MARIO REDENTOR MOSLARES', 'detail' => 'APPROVE', 'timestamp' => '2026-08-05 14:28:59'],
            ['status' => 'To Pay', 'by' => 'GENCYS - Angelyn Macabasag', 'detail' => 'to pay', 'timestamp' => '2026-08-05 14:38:26'],
        ],
    ]));

    $order = PurchasedOrder::where('control_no', 'CN-TP910')->firstOrFail();

    expect($order->paid_at)->toBeNull()
        ->and($order->statusLogs)->toHaveCount(2);
});

test('a partial payment still counts as the date money moved', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = poSyncItem($workspace);

    // The ERP writes the split into the free-text detail, never the label.
    syncOrder($workspace, $raw, $item, erpOrder([
        'statusLogs' => [
            ['status' => 'Paid', 'by' => 'RENZ LAICA MERCADO', 'detail' => "Paid-50%-14,500\n2026-07-14 15:40:18", 'timestamp' => '2026-07-14 15:40:18'],
        ],
    ]));

    expect(PurchasedOrder::where('control_no', 'CN-TP910')->firstOrFail()->paid_at->toDateTimeString())
        ->toBe('2026-07-14 15:40:18');
});

test('re-syncing replaces the trail rather than doubling it', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = poSyncItem($workspace);

    syncOrder($workspace, $raw, $item, erpOrder());
    syncOrder($workspace, $raw, $item, erpOrder());

    $order = PurchasedOrder::where('control_no', 'CN-TP910')->firstOrFail();

    expect($order->statusLogs)->toHaveCount(4)
        ->and(PurchasedOrderStatusLog::count())->toBe(4);
});

test('a withdrawn Paid entry clears the paid date', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = poSyncItem($workspace);

    syncOrder($workspace, $raw, $item, erpOrder());
    expect(PurchasedOrder::where('control_no', 'CN-TP910')->firstOrFail()->paid_at)->not->toBeNull();

    // The ERP corrected itself: the payment entry is gone from the trail.
    syncOrder($workspace, $raw, $item, erpOrder([
        'statusLogs' => [
            ['status' => 'Approve', 'by' => 'RENZ LAICA MERCADO', 'detail' => 'approve', 'timestamp' => '2026-07-23 17:32:32'],
        ],
    ]));

    expect(PurchasedOrder::where('control_no', 'CN-TP910')->firstOrFail()->paid_at)->toBeNull();
});

test('a payload with no statusLogs key leaves an existing trail alone', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = poSyncItem($workspace);

    syncOrder($workspace, $raw, $item, erpOrder());

    // Absent means "not sent", not "cleared" — an older n8n flow must not wipe
    // what a newer one recorded.
    $withoutLogs = erpOrder();
    unset($withoutLogs['statusLogs']);
    syncOrder($workspace, $raw, $item, $withoutLogs);

    $order = PurchasedOrder::where('control_no', 'CN-TP910')->firstOrFail();

    expect($order->statusLogs)->toHaveCount(4)
        ->and($order->paid_at)->not->toBeNull();
});

test('deleting an order takes its status trail with it', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = poSyncItem($workspace);

    syncOrder($workspace, $raw, $item, erpOrder());

    PurchasedOrder::where('control_no', 'CN-TP910')->firstOrFail()->delete();

    expect(PurchasedOrderStatusLog::count())->toBe(0);
});

test('the dashboard open-PO payload carries the paid date and supplier', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = poSyncItem($workspace);

    syncOrder($workspace, $raw, $item, erpOrder());

    $data = test()->actingAs($owner)
        ->getJson("/api/workspaces/{$workspace->slug}/inventory/dashboard/open-purchase-orders")
        ->assertOk()
        ->json();

    // Date-only: the client formats it, and a time of day would let a timezone
    // shift the displayed day.
    expect($data['lines'][0]['paid_date'])->toBe('2026-07-24')
        ->and($data['lines'][0]['supplier'])->toBe('Kintara Manuf Ventures Inc');
});

test('the PO drill-down carries the paid date, supplier and trail', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = poSyncItem($workspace);

    syncOrder($workspace, $raw, $item, erpOrder());
    $order = PurchasedOrder::where('control_no', 'CN-TP910')->firstOrFail();

    $data = test()->actingAs($owner)
        ->getJson("/api/workspaces/{$workspace->slug}/inventory/dashboard/purchase-orders/{$order->id}/lines")
        ->assertOk()
        ->json();

    expect($data['order']['paid_date'])->toBe('2026-07-24')
        ->and($data['order']['supplier'])->toBe('Kintara Manuf Ventures Inc')
        ->and($data['status_logs'])->toHaveCount(4)
        ->and($data['status_logs'][0]['status'])->toBe('Approve');
});
