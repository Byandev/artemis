<?php

use App\Http\Controllers\API\Workspace\AnalyticsController;
use App\Http\Controllers\API\Workspace\CSRController;
use App\Http\Controllers\API\Workspace\CsrPerformanceController;
use App\Http\Controllers\API\Workspace\PageController;
use App\Http\Controllers\API\Workspace\ProductController;
use App\Http\Controllers\API\Workspace\ShopController;
use App\Http\Controllers\API\Workspace\TeamController;
use App\Http\Controllers\API\Workspace\UserController;
use App\Http\Controllers\API\Workspace\VideoEditorDashboardController;
use Modules\Inventory\Http\Controllers\Api\InventoryDashboardStatsController;
use Modules\Inventory\Http\Controllers\Api\PurchaseOrderFlowController;

// Unauthenticated public endpoints (leaderboards, CSR performance widgets)
Route::group(['prefix' => 'api/public', 'as' => 'api.public.'], function () {
    Route::get('/workspaces/{workspace}/csrs/performance', [CsrPerformanceController::class, 'publicIndex'])
        ->middleware('throttle:csr-public-performance')
        ->name('workspaces.csrs.performance.index');

    Route::get('/workspaces/{workspace}/csrs', [CsrPerformanceController::class, 'publicCsrIndex'])
        ->middleware('throttle:csr-public-csrs')
        ->name('workspaces.csrs.index');

    Route::get('/leaderboards', [CsrPerformanceController::class, 'leaderboards']);
    Route::get('/leaderboards/group-by-called', [CsrPerformanceController::class, 'leaderboardsGroupByCalled']);
    Route::get('/leaderboards/group-by-delivered', [CsrPerformanceController::class, 'leaderboardsGroupByDelivered']);
    Route::get('/leaderboards/schedules', [CsrPerformanceController::class, 'csrSchedules']);
});

// Session-authenticated internal API (called from the browser/Inertia frontend)
Route::group(['prefix' => 'api', 'as' => 'api.', 'middleware' => ['auth']], function () {
    Route::group(['prefix' => 'workspaces/{workspace}', 'as' => 'workspaces.'], function () {
        Route::get('/csrs/performance', [CsrPerformanceController::class, 'index'])->name('csrs.performance.index');
        Route::get('/csrs/daily-records', [CSRController::class, 'dailyRecords'])->name('csrs.daily-records.index');
        Route::get('/csrs/stats/total-sales', [CSRController::class, 'statTotalSales']);
        Route::get('/csrs/stats/total-orders', [CSRController::class, 'statTotalOrders']);
        Route::get('/csrs/stats/total-delivered', [CSRController::class, 'statTotalDelivered']);
        Route::get('/csrs/stats/total-returning', [CSRController::class, 'statTotalReturning']);
        Route::get('/csrs/stats/total-rts', [CSRController::class, 'statTotalRts']);
        Route::get('/csrs/stats/total-rmo-called', [CSRController::class, 'statTotalRmoCalled']);
        // CSR Analytics cards. Same one-endpoint-per-card shape as the stats
        // above; they read the workspace's orders (the dashboard's source)
        // rather than the nightly per-CSR rollup.
        Route::get('/csrs/stats/analytics-sales', [CSRController::class, 'analyticsSales']);
        Route::get('/csrs/stats/analytics-rts', [CSRController::class, 'analyticsRts']);
        Route::get('/csrs/stats/analytics-rmo-called', [CSRController::class, 'analyticsRmoCalled']);
        Route::get('/csrs/stats/analytics-rmo-time', [CSRController::class, 'analyticsRmoTime']);
        Route::get('/csrs/stats/analytics-calls-placed', [CSRController::class, 'analyticsCallsPlaced']);
        Route::get('/csrs/stats/analytics-real-conversations', [CSRController::class, 'analyticsRealConversations']);
        Route::get('/csrs/stats/analytics-reach-rate', [CSRController::class, 'analyticsReachRate']);
        Route::get('/csrs/stats/analytics-longest-call', [CSRController::class, 'analyticsLongestCall']);
        // Leaders for the period — who came top, same source as the cards above.
        Route::get('/csrs/stats/analytics-leader-sales', [CSRController::class, 'analyticsLeaderSales']);
        Route::get('/csrs/stats/analytics-leader-rts', [CSRController::class, 'analyticsLeaderRts']);
        Route::get('/csrs/stats/analytics-leader-rmo-called', [CSRController::class, 'analyticsLeaderRmoCalled']);
        Route::get('/csrs/stats/analytics-leader-rmo-duration', [CSRController::class, 'analyticsLeaderRmoDuration']);
        Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/shops', [ShopController::class, 'index'])->name('shops.index');
        Route::get('/pages', [PageController::class, 'index'])->name('pages.index');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/leaderboards', [CsrPerformanceController::class, 'leaderboards'])->name('leaderboards.index');

        Route::prefix('video-editor')->name('video-editor.')->group(function () {
            Route::get('/kpi/total', [VideoEditorDashboardController::class, 'totalCreatives'])->name('kpi.total');
            Route::get('/kpi/awaiting-review', [VideoEditorDashboardController::class, 'awaitingReview'])->name('kpi.awaiting-review');
            Route::get('/kpi/needs-revision', [VideoEditorDashboardController::class, 'needsRevision'])->name('kpi.needs-revision');
            Route::get('/kpi/approved', [VideoEditorDashboardController::class, 'approved'])->name('kpi.approved');
            Route::get('/ads', [VideoEditorDashboardController::class, 'ads'])->name('ads');
            Route::get('/pipeline', [VideoEditorDashboardController::class, 'pipeline'])->name('pipeline');
            Route::get('/revision-list', [VideoEditorDashboardController::class, 'revisionList'])->name('revision-list');
            Route::get('/throughput', [VideoEditorDashboardController::class, 'throughput'])->name('throughput');
            Route::get('/leaderboard', [VideoEditorDashboardController::class, 'leaderboard'])->name('leaderboard');
            Route::get('/recent-activity', [VideoEditorDashboardController::class, 'recentActivity'])->name('recent-activity');
            Route::get('/calendar', [VideoEditorDashboardController::class, 'calendar'])->name('calendar');
        });

        // Inventory dashboard — one endpoint per KPI so each loads, skeletons
        // and refreshes independently. See InventoryDashboardStatsController.
        Route::prefix('inventory/dashboard/kpi')->name('inventory.dashboard.kpi.')->group(function () {
            Route::get('/inventory-items', [InventoryDashboardStatsController::class, 'inventoryItems'])->name('inventory-items');
            Route::get('/total-stocks', [InventoryDashboardStatsController::class, 'totalStocks'])->name('total-stocks');
            Route::get('/unfulfilled', [InventoryDashboardStatsController::class, 'unfulfilled'])->name('unfulfilled');
            Route::get('/open-pos', [InventoryDashboardStatsController::class, 'openPos'])->name('open-pos');
        });

        Route::get('/inventory/dashboard/movement', [InventoryDashboardStatsController::class, 'movement'])
            ->name('inventory.dashboard.movement');

        Route::get('/inventory/dashboard/high-unfulfilled', [InventoryDashboardStatsController::class, 'highUnfulfilled'])
            ->name('inventory.dashboard.high-unfulfilled');

        Route::get('/inventory/dashboard/low-stock', [InventoryDashboardStatsController::class, 'lowStock'])
            ->name('inventory.dashboard.low-stock');

        Route::get('/inventory/dashboard/open-purchase-orders', [InventoryDashboardStatsController::class, 'openPurchaseOrders'])
            ->name('inventory.dashboard.open-purchase-orders');

        Route::get('/inventory/dashboard/purchase-orders/{purchasedOrder}/lines', [InventoryDashboardStatsController::class, 'purchaseOrderLines'])
            ->name('inventory.dashboard.purchase-order-lines');

        Route::get('/inventory/dashboard/delivery-lead-time', [InventoryDashboardStatsController::class, 'deliveryLeadTime'])
            ->name('inventory.dashboard.delivery-lead-time');

        // Purchase-order flow: where ordered stock is sitting and who is holding
        // it. One endpoint per panel, same as the rest of the dashboard, so a
        // slow panel never blocks the others.
        Route::prefix('inventory/dashboard/po-flow')->name('inventory.dashboard.po-flow.')->group(function () {
            Route::get('/kpi', [PurchaseOrderFlowController::class, 'kpi'])->name('kpi');
            Route::get('/bottleneck', [PurchaseOrderFlowController::class, 'bottleneck'])->name('bottleneck');
            Route::get('/pipeline', [PurchaseOrderFlowController::class, 'pipeline'])->name('pipeline');
            Route::get('/worklist', [PurchaseOrderFlowController::class, 'worklist'])->name('worklist');
            Route::get('/supplier-deliveries', [PurchaseOrderFlowController::class, 'supplierDeliveries'])->name('supplier-deliveries');
            Route::get('/stage-timings', [PurchaseOrderFlowController::class, 'stageTimings'])->name('stage-timings');
            Route::get('/unfulfilled-split', [PurchaseOrderFlowController::class, 'unfulfilledSplit'])->name('unfulfilled-split');
        });
    });
});

Route::group(['prefix' => 'api/v1/workspace', 'as' => 'api.v1.workspace', 'middleware' => ['auth', 'workspace']], function () {
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/breakdown', [AnalyticsController::class, 'breakdown'])->name('analytics.breakdown');
    Route::get('/analytics/per-page', [AnalyticsController::class, 'perPage'])->name('analytics.perPage');
    Route::get('/analytics/per-shop', [AnalyticsController::class, 'perShop'])->name('analytics.perShop');
    Route::get('/analytics/per-user', [AnalyticsController::class, 'perUser'])->name('analytics.perUser');
});
