<?php

use App\Models\Page;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Modules\Pancake\Jobs\SyncOrder;
use Modules\Pancake\Models\Order;

/**
 * SyncOrder skipping an order Pancake handed back unchanged.
 *
 * The hourly fetch and the shipped pulls re-read orders that have not moved
 * since the last pass. A matching payload fingerprint should cost one lookup,
 * not a rewrite of the order and everything hanging off it.
 */
function pancakeOrderPayload(Page $page, array $overrides = []): array
{
    return array_merge([
        'id' => 1001,
        'shop_id' => $page->shop_id,
        'page_id' => null,
        'order_sources' => -1,
        'order_sources_name' => 'Facebook',
        'status' => 1,
        'status_name' => 'Confirmed',
        'order_currency' => 'PHP',
        'total_price' => 500,
        'total_discount' => 0,
        'total_price_after_sub_discount' => 500,
        'ad_id' => null,
        'conversation_id' => '111_222',
        'inserted_at' => now()->subDays(3)->utc()->format('Y-m-d\TH:i:s.u'),
        'status_history' => [],
        'customer' => ['customer_id' => 'cust-1'],
        'items' => [],
        'shipping_address' => [
            'province_name' => 'Metro Manila',
            'district_name' => 'Quezon City',
            'commune_name' => 'Bagong Pag-asa',
            'address' => '1 Main St',
            'full_name' => 'Juan Dela Cruz',
            'full_address' => '1 Main St, Quezon City',
            'phone_number' => '09170000000',
        ],
    ], $overrides);
}

function runSyncOrder(Workspace $workspace, Page $page, array $data): void
{
    app()->call([new SyncOrder($workspace, $page, $data), 'handle']);
}

test('an unchanged payload skips the rewrite', function () {
    $this->freezeTime();
    $workspace = Workspace::factory()->create();
    $page = Page::factory()->forWorkspace($workspace)->create();
    $payload = pancakeOrderPayload($page);

    runSyncOrder($workspace, $page, $payload);

    $order = Order::where('order_number', 1001)->firstOrFail();
    expect($order->sync_hash)->not->toBeNull();

    DB::enableQueryLog();
    runSyncOrder($workspace, $page, $payload);
    $writes = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn ($sql) => preg_match('/^\s*(insert|update|delete)/i', $sql));
    DB::disableQueryLog();

    expect($writes)->toBeEmpty();
});

test('the same payload with its lists reordered still skips', function () {
    $this->freezeTime();
    $workspace = Workspace::factory()->create();
    $page = Page::factory()->forWorkspace($workspace)->create();
    $history = [
        ['status' => 0, 'updated_at' => now()->subDays(3)->utc()->format('Y-m-d\TH:i:s')],
        ['status' => 1, 'updated_at' => now()->subDays(2)->utc()->format('Y-m-d\TH:i:s')],
    ];

    runSyncOrder($workspace, $page, pancakeOrderPayload($page, ['status_history' => $history]));
    $firstHash = Order::where('order_number', 1001)->value('sync_hash');

    runSyncOrder($workspace, $page, pancakeOrderPayload($page, ['status_history' => array_reverse($history)]));

    expect(Order::where('order_number', 1001)->value('sync_hash'))->toBe($firstHash);
});

test('a changed payload syncs in full', function () {
    $workspace = Workspace::factory()->create();
    $page = Page::factory()->forWorkspace($workspace)->create();

    runSyncOrder($workspace, $page, pancakeOrderPayload($page));
    $firstHash = Order::where('order_number', 1001)->value('sync_hash');

    runSyncOrder($workspace, $page, pancakeOrderPayload($page, ['status' => 2, 'status_name' => 'Shipped']));

    $order = Order::where('order_number', 1001)->firstOrFail();
    expect($order->status)->toBe(2)
        ->and($order->sync_hash)->not->toBe($firstHash);
});
