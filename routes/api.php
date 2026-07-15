<?php

use App\Http\Controllers\PublicApi\CallLogController;
use App\Http\Controllers\PublicApi\CallLogV2Controller;
use App\Http\Controllers\PublicApi\CsrDailyRecordController;
use App\Http\Controllers\PublicApi\HealthController;
use App\Http\Controllers\PublicApi\InventoryItemController;
use App\Http\Controllers\PublicApi\PageController;
use App\Http\Controllers\PublicApi\PurchaseOrderController;
use App\Http\Controllers\PublicApi\RmoOrderController;
use App\Http\Controllers\PublicApi\RmoOrderV2Controller;
use App\Http\Controllers\PublicApi\ShopController;
use App\Http\Controllers\PublicApi\ShopScanReturnController;
use App\Http\Controllers\PublicApi\TransactionHistoryController;
use App\Http\Controllers\PublicApi\UserController;
use Modules\GencysERP\Http\Controllers\Api\DailySalesTrackerController;
use Modules\GencysERP\Http\Controllers\Api\InternController as GencysInternApiController;
use Modules\GencysERP\Http\Controllers\Api\InternDailyRecordController as GencysInternDailyRecordApiController;
use Modules\GencysERP\Http\Controllers\Api\PageController as GencysPageApiController;
use Modules\GencysERP\Http\Controllers\Api\UnitCodeInventoryController as GencysUnitCodeInventoryApiController;
use Modules\Inventory\Http\Controllers\Api\UnitCodeController as InventoryUnitCodeApiController;

Route::group(['prefix' => 'v1/public', 'as' => 'api.v1.public.', 'middleware' => ['api.key']], function () {
    Route::get('/health', HealthController::class)->name('health');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/csr-daily-records', [CsrDailyRecordController::class, 'store'])->name('csr-daily-records.store');
    Route::get('/pages', [PageController::class, 'index'])->name('pages.index');
    Route::get('/shops', [ShopController::class, 'index'])->name('shops.index');
    Route::post('/shops/scan-return', [ShopScanReturnController::class, 'scan'])->name('shops.scan-return');
    Route::post('/rmo-orders/login', [RmoOrderController::class, 'login'])->name('rmo-orders.login');
    Route::get('/rmo-orders', [RmoOrderController::class, 'assignedOrders'])->name('rmo-orders.index');

    Route::post('/rmo-orders/sync-call-tracking', [RmoOrderController::class, 'syncCallTracking'])->name('rmo-orders.call-tracking.sync');

    Route::post('/call-logs/sync', [CallLogController::class, 'sync'])->name('call-logs.sync');
    Route::get('/call-logs/kpi', [CallLogController::class, 'kpi'])->name('call-logs.kpi');
    Route::get('/call-logs/list', [CallLogController::class, 'list'])->name('call-logs.list');
    Route::get('/call-logs/summary', [CallLogController::class, 'summary'])->name('call-logs.summary');

    Route::get('/inventory-items/keywords', [InventoryItemController::class, 'keywords'])->name('inventory-items.keywords');
    Route::post('/inventory-items/sync', [InventoryItemController::class, 'sync'])->name('inventory-items.sync');

    Route::post('/purchase-orders/bulk-sync', [PurchaseOrderController::class, 'bulkSync'])->name('purchase-orders.bulk-sync');
    Route::post('/inventory-items/transactions/bulk-sync', [TransactionHistoryController::class, 'bulkSync'])->name('transaction-history.bulk-sync');

    // Inventory unit codes callback. The ERP sync (via n8n) posts the scraped
    // unit codes (with their items) here, authenticating with the workspace API
    // key header — that alone identifies the workspace.
    Route::post('/inventory/unit-codes/bulk-sync', [InventoryUnitCodeApiController::class, 'bulkSync'])->name('inventory.unit-codes.bulk-sync');
});

// GencysERP daily sales tracker callback. n8n posts the scraped rows here and
// authenticates with the api_key embedded in the body (not a header), so this
// sits outside the api.key middleware group.
Route::post('v1/public/gencys/daily-sales-tracker', [DailySalesTrackerController::class, 'store'])
    ->name('api.v1.public.gencys.daily-sales-tracker.store');

// GencysERP unit code inventories callback. n8n posts the scraped inventory
// items for a unit code here and authenticates with the api_key embedded in the
// body (not a header), so this sits outside the api.key middleware group.
Route::post('v1/public/gencys/unit-code-inventories', [GencysUnitCodeInventoryApiController::class, 'store'])
    ->name('api.v1.public.gencys.unit-code-inventories.store');

// GencysERP interns callback. n8n posts the scraped interns here and
// authenticates with the api_key embedded in the body (not a header), so this
// sits outside the api.key middleware group.
Route::post('v1/public/gencys/interns', [GencysInternApiController::class, 'store'])
    ->name('api.v1.public.gencys.interns.store');

// GencysERP intern daily records callback. n8n posts the scraped per-intern
// daily records here, authenticating with the api_key embedded in the body.
Route::post('v1/public/gencys/intern-daily-records', [GencysInternDailyRecordApiController::class, 'store'])
    ->name('api.v1.public.gencys.intern-daily-records.store');

// GencysERP pages callback. n8n authenticates via the api_key header (the
// api.key middleware resolves the workspace) and posts just the array of
// scraped pages as the body.
Route::post('v1/public/gencys/pages', [GencysPageApiController::class, 'store'])
    ->middleware('api.key')
    ->name('api.v1.public.gencys.pages.store');

// GencysERP page-detail callback. n8n posts the scraped detail (Pancake shop id,
// api token, fb page id, url) for a single page, keyed on gencys_pages.page_id,
// authenticating with the api_key embedded in the body.
Route::post('v1/public/gencys/page-details', [GencysPageApiController::class, 'storeDetails'])
    ->name('api.v1.public.gencys.page-details.store');

// v2 — new mobile app (login with users.id, filter by assignee_user_id)
Route::group(['prefix' => 'v2/public', 'as' => 'api.v2.public.', 'middleware' => ['api.key']], function () {
    Route::post('/rmo-orders/login', [RmoOrderV2Controller::class, 'login'])->name('rmo-orders.login');
    Route::get('/rmo-orders', [RmoOrderV2Controller::class, 'assignedOrders'])->name('rmo-orders.index');
    Route::post('/rmo-orders/sync-call-tracking', [RmoOrderV2Controller::class, 'syncCallTracking'])->name('rmo-orders.call-tracking.sync');

    Route::post('/call-logs/sync', [CallLogV2Controller::class, 'sync'])->name('call-logs.sync');
    Route::get('/call-logs/kpi', [CallLogV2Controller::class, 'kpi'])->name('call-logs.kpi');
    Route::get('/call-logs/list', [CallLogV2Controller::class, 'list'])->name('call-logs.list');
    Route::get('/call-logs/summary', [CallLogV2Controller::class, 'summary'])->name('call-logs.summary');
});
