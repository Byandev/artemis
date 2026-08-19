<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchasedOrder;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Local copies of the sync helpers: Pest only shares functions declared in
 * Pest.php, so a sibling test file's helpers are not in scope here.
 */
function stageSyncItem($workspace): InventoryItem
{
    return InventoryItem::create([
        'workspace_id' => $workspace->id,
        'sku' => 'SKU-STAGE',
        'is_active' => true,
    ]);
}

function stageSyncOrder($workspace, string $rawKey, InventoryItem $item, array $po): void
{
    test()->postJson('/api/v1/public/purchase-orders/bulk-sync', [
        'data' => [[
            'id' => $item->id,
            'sync_run_id' => null,
            'purchased_orders' => [$po],
        ]],
    ], ['Authorization' => 'Bearer '.$rawKey])->assertOk();
}

/** A fully-purchased order, trimmed from the real CN-TP910 payload. */
function stageErpOrder(array $overrides = []): array
{
    return array_merge([
        'control_no' => 'CN-TP910',
        'issue_date' => '2026-07-23',
        'delivery_no' => 'DN-TP910',
        'cust_po_no' => 'CPO-TP910',
        'delivery_fee' => 750,
        'total_amount' => 10890,
        'status' => 6,
        'supplier' => 'ALL',
        'items' => [['count' => 400, 'amount' => 25.35, 'total_amount' => 10140]],
        'deliveries' => [['qty' => 399, 'created_at' => '2026-08-01 12:00:33']],
        'statusLogs' => [
            ['status' => 'Approve', 'by' => 'GENCYS - Angelyn Macabasag', 'detail' => 'approve', 'timestamp' => '2026-07-23 17:32:32'],
            ['status' => 'To Pay', 'by' => 'GENCYS - Angelyn Macabasag', 'detail' => 'to pay', 'timestamp' => '2026-07-23 17:33:52'],
            ['status' => 'Paid', 'by' => 'RENZ LAICA MERCADO', 'detail' => 'PAID', 'timestamp' => '2026-07-24 16:23:30'],
            ['status' => 'For Purchase', 'by' => 'GENCYS - Angelyn Macabasag', 'detail' => 'for purchased', 'timestamp' => '2026-07-24 16:52:25'],
            ['status' => 'Purchased', 'by' => 'GENCYS - Angelyn Macabasag', 'detail' => 'purchased', 'timestamp' => '2026-07-24 16:52:34'],
        ],
    ], $overrides);
}

/** The stage stamps as the ERP sends them, for that same order. */
function erpStageStamps(array $overrides = []): array
{
    return array_merge([
        'approved_at' => '2026-07-23 17:32:32',
        'to_pay_at' => '2026-07-23 17:33:52',
        'paid_at' => '2026-07-24 16:23:30',
        'for_purchase_at' => '2026-07-24 16:52:25',
        'purchased_at' => '2026-07-24 16:52:34',
    ], $overrides);
}

test('the sync stores every stage timestamp the ERP sends', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = stageSyncItem($workspace);

    stageSyncOrder($workspace, $raw, $item, stageErpOrder(erpStageStamps()));

    $order = PurchasedOrder::where('control_no', 'CN-TP910')->firstOrFail();

    expect($order->approved_at->toDateTimeString())->toBe('2026-07-23 17:32:32')
        ->and($order->to_pay_at->toDateTimeString())->toBe('2026-07-23 17:33:52')
        ->and($order->paid_at->toDateTimeString())->toBe('2026-07-24 16:23:30')
        ->and($order->for_purchase_at->toDateTimeString())->toBe('2026-07-24 16:52:25')
        ->and($order->purchased_at->toDateTimeString())->toBe('2026-07-24 16:52:34');
});

test('stages the order has not reached come back null', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = stageSyncItem($workspace);

    // A raised-but-untouched order: the real "status": 1 case, every stamp null.
    stageSyncOrder($workspace, $raw, $item, stageErpOrder([
        'status' => 1,
        'statusLogs' => [],
        ...erpStageStamps([
            'approved_at' => null,
            'to_pay_at' => null,
            'paid_at' => null,
            'for_purchase_at' => null,
            'purchased_at' => null,
        ]),
    ]));

    $order = PurchasedOrder::where('control_no', 'CN-TP910')->firstOrFail();

    expect($order->approved_at)->toBeNull()
        ->and($order->to_pay_at)->toBeNull()
        ->and($order->paid_at)->toBeNull()
        ->and($order->for_purchase_at)->toBeNull()
        ->and($order->purchased_at)->toBeNull();
});

test('an order paid but not yet released keeps the later stages empty', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = stageSyncItem($workspace);

    // Half-paid and waiting on purchasing — CN-TP958 from the payload.
    stageSyncOrder($workspace, $raw, $item, stageErpOrder([
        'status' => 4,
        ...erpStageStamps([
            'approved_at' => '2026-08-06 19:39:26',
            'to_pay_at' => '2026-08-06 19:39:36',
            'paid_at' => '2026-08-06 19:40:13',
            'for_purchase_at' => null,
            'purchased_at' => null,
        ]),
    ]));

    $order = PurchasedOrder::where('control_no', 'CN-TP910')->firstOrFail();

    expect($order->paid_at->toDateTimeString())->toBe('2026-08-06 19:40:13')
        ->and($order->for_purchase_at)->toBeNull()
        ->and($order->purchased_at)->toBeNull();
});

test('the ERP’s own paid_at wins over the one in the trail', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = stageSyncItem($workspace);

    // The two disagree only when the ERP knows something the log does not — and
    // then the field it sends deliberately is the one to trust.
    stageSyncOrder($workspace, $raw, $item, stageErpOrder([
        'paid_at' => '2026-07-20 09:00:00',
        'statusLogs' => [
            ['status' => 'Paid', 'by' => 'RENZ LAICA MERCADO', 'detail' => 'PAID', 'timestamp' => '2026-07-24 16:23:30'],
        ],
    ]));

    expect(PurchasedOrder::where('control_no', 'CN-TP910')->firstOrFail()->paid_at->toDateTimeString())
        ->toBe('2026-07-20 09:00:00');
});

test('a null paid_at from the ERP clears it, trail or no trail', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = stageSyncItem($workspace);

    stageSyncOrder($workspace, $raw, $item, stageErpOrder(erpStageStamps()));
    expect(PurchasedOrder::where('control_no', 'CN-TP910')->firstOrFail()->paid_at)->not->toBeNull();

    // Sent as null means "not paid", even though the Paid log is still there —
    // an explicit null is the ERP correcting itself, not an omission.
    stageSyncOrder($workspace, $raw, $item, stageErpOrder(erpStageStamps(['paid_at' => null])));

    expect(PurchasedOrder::where('control_no', 'CN-TP910')->firstOrFail()->paid_at)->toBeNull();
});

test('a payload with no stage fields leaves the stored stamps alone', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = stageSyncItem($workspace);

    stageSyncOrder($workspace, $raw, $item, stageErpOrder(erpStageStamps()));

    // An older n8n flow that predates these fields must not wipe what a newer
    // one recorded — absent is "not sent", the same rule the trail follows.
    stageSyncOrder($workspace, $raw, $item, stageErpOrder());

    $order = PurchasedOrder::where('control_no', 'CN-TP910')->firstOrFail();

    expect($order->approved_at->toDateTimeString())->toBe('2026-07-23 17:32:32')
        ->and($order->purchased_at->toDateTimeString())->toBe('2026-07-24 16:52:34');
});

test('paid_at still falls back to the trail when the ERP omits the field', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $item = stageSyncItem($workspace);

    // stageErpOrder() carries a trail but no paid_at field.
    stageSyncOrder($workspace, $raw, $item, stageErpOrder());

    expect(PurchasedOrder::where('control_no', 'CN-TP910')->firstOrFail()->paid_at->toDateTimeString())
        ->toBe('2026-07-24 16:23:30');
});
