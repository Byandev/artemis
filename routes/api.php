<?php

use App\Http\Controllers\API\Workspace\AnalyticsController;
use App\Http\Controllers\API\InventoryPurchasedOrderController;
use App\Http\Controllers\API\Workspace\CsrPerformanceController;
use App\Http\Controllers\API\Workspace\PageController;
use App\Http\Controllers\API\Workspace\ProductController;
use App\Http\Controllers\API\Workspace\ShopController;
use App\Http\Controllers\API\Workspace\TeamController;
use App\Http\Controllers\API\Workspace\UserController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'api/public', 'as' => 'api.public.', 'middleware' => ['throttle:60,1']], function () {
    Route::get('/workspaces/{workspace}/csrs/performance', [CsrPerformanceController::class, 'publicIndex'])->name('workspaces.csrs.performance.index');
});

Route::group(['prefix' => 'api', 'as' => 'api.', 'middleware' => ['auth']], function () {
    Route::group(['prefix' => 'workspaces/{workspace}', 'as' => 'workspaces.'], function () {
        Route::get('/inventory/purchased-orders', [InventoryPurchasedOrderController::class, 'index'])
            ->middleware('throttle:120,1')
            ->name('inventory.purchased-orders.index');
        Route::patch('/inventory/purchased-orders/{order}/status', [InventoryPurchasedOrderController::class, 'updateStatus'])
            ->middleware('throttle:30,1')
            ->name('inventory.purchased-orders.update-status'); 
        Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/shops', [ShopController::class, 'index'])->name('shops.index');
        Route::get('/pages', [PageController::class, 'index'])->name('pages.index');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
    });
});

Route::group(['prefix' => 'api/v1/workspace', 'as' => 'api.v1.workspace', 'middleware' => ['auth', 'workspace']], function () {
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/breakdown', [AnalyticsController::class, 'breakdown'])->name('analytics.breakdown');
});
 