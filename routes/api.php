<?php

Route::group(['prefix' => 'v1/public', 'as' => 'api.v1.public.', 'middleware' => ['api.key']], function () {
    Route::get('/health',             \App\Http\Controllers\PublicApi\HealthController::class)->name('health');
    Route::get('/users',              [\App\Http\Controllers\PublicApi\UserController::class, 'index'])->name('users.index');
    Route::post('/csr-daily-records', [\App\Http\Controllers\PublicApi\CsrDailyRecordController::class, 'store'])->name('csr-daily-records.store');
    Route::post('/rmo-orders/login',  [\App\Http\Controllers\PublicApi\RmoOrderController::class, 'login'])->name('rmo-orders.login');
    Route::get('/pages',               [\App\Http\Controllers\PublicApi\PageController::class, 'index'])->name('pages.index');
    Route::get('/shops',               [\App\Http\Controllers\PublicApi\ShopController::class, 'index'])->name('shops.index');
    Route::get('/rmo-orders',          [\App\Http\Controllers\PublicApi\RmoOrderController::class, 'assignedOrders'])->name('rmo-orders.index');
    Route::post('/rmo-orders/sync-call-tracking', [\App\Http\Controllers\PublicApi\RmoOrderController::class, 'syncCallTracking'])->name('rmo-orders.call-tracking.sync');
});
