<?php

use App\Http\Controllers\PublicApi\CallLogController;
use App\Http\Controllers\PublicApi\CsrDailyRecordController;
use App\Http\Controllers\PublicApi\CsrTrackerController;
use App\Http\Controllers\PublicApi\HealthController;
use App\Http\Controllers\PublicApi\InventoryItemController;
use App\Http\Controllers\PublicApi\PageController;
use App\Http\Controllers\PublicApi\RmoOrderController;
use App\Http\Controllers\PublicApi\RmoOrderV2Controller;
use App\Http\Controllers\PublicApi\ShopController;
use App\Http\Controllers\PublicApi\ShopScanReturnController;
use App\Http\Controllers\PublicApi\UserController;

Route::group(['prefix' => 'v1/public', 'as' => 'api.v1.public.', 'middleware' => ['api.key']], function () {
    Route::get('/health', HealthController::class)->name('health');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/csr-daily-records', [CsrDailyRecordController::class, 'store'])->name('csr-daily-records.store');
    Route::get('/pages', [PageController::class, 'index'])->name('pages.index');
    Route::get('/shops', [ShopController::class, 'index'])->name('shops.index');
    Route::post('/shops/scan-return', [ShopScanReturnController::class, 'scan'])->name('shops.scan-return');
    Route::get('/rmo-orders/login', [RmoOrderController::class, 'login'])->name('rmo-orders.login');
    Route::get('/rmo-orders', [RmoOrderController::class, 'assignedOrders'])->name('rmo-orders.index');

    Route::post('/rmo-orders/sync-call-tracking', [RmoOrderController::class, 'syncCallTracking'])->name('rmo-orders.call-tracking.sync');

    Route::post('/call-logs/sync', [CallLogController::class, 'sync'])->name('call-logs.sync');
    Route::get('/call-logs/kpi', [CallLogController::class, 'kpi'])->name('call-logs.kpi');
    Route::get('/call-logs/list', [CallLogController::class, 'list'])->name('call-logs.list');
    Route::get('/call-logs/summary', [CallLogController::class, 'summary'])->name('call-logs.summary');

    Route::get('/inventory-items/keywords', [InventoryItemController::class, 'keywords'])->name('inventory-items.keywords');
    Route::post('/inventory-items/sync', [InventoryItemController::class, 'sync'])->name('inventory-items.sync');
});

// v2 — new mobile app (login with users.id, filter by assignee_user_id)
Route::group(['prefix' => 'v2/public', 'as' => 'api.v2.public.', 'middleware' => ['api.key']], function () {
    Route::post('/rmo-orders/login', [RmoOrderV2Controller::class, 'login'])->name('rmo-orders.login');
    Route::get('/rmo-orders', [RmoOrderV2Controller::class, 'assignedOrders'])->name('rmo-orders.index');
    Route::post('/rmo-orders/sync-call-tracking', [RmoOrderV2Controller::class, 'syncCallTracking'])->name('rmo-orders.call-tracking.sync');
});

