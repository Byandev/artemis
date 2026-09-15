<?php

use App\Http\Controllers\API\Workspace\AnalyticsController;
use App\Http\Controllers\API\Workspace\CSRController;
use App\Http\Controllers\API\Workspace\CsrDashboardController;
use App\Http\Controllers\API\Workspace\CsrPerformanceController;
use App\Http\Controllers\API\Workspace\PageController;
use App\Http\Controllers\API\Workspace\ParcelJourneyStatsController;
use App\Http\Controllers\API\Workspace\ProductController;
use App\Http\Controllers\API\Workspace\SalesMarketingDashboardController;
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
        Route::get('/csrs/stats/analytics-total-rmo-called', [CSRController::class, 'analyticsTotalRmoCalled']);
        Route::get('/csrs/stats/analytics-rmo-call-time', [CSRController::class, 'analyticsRmoCallTime']);
        Route::get('/csrs/stats/analytics-rmo-real-conversations', [CSRController::class, 'analyticsRmoRealConversations']);
        Route::get('/csrs/stats/analytics-rmo-hit-rate', [CSRController::class, 'analyticsRmoHitRate']);
        Route::get('/csrs/stats/analytics-rmo-time', [CSRController::class, 'analyticsRmoTime']);
        Route::get('/csrs/stats/analytics-calls-placed', [CSRController::class, 'analyticsCallsPlaced']);
        Route::get('/csrs/stats/analytics-real-conversations', [CSRController::class, 'analyticsRealConversations']);
        Route::get('/csrs/stats/analytics-confirmed-risky-orders', [CSRController::class, 'analyticsConfirmedRiskyOrders']);
        Route::get('/csrs/stats/analytics-verified-orders', [CSRController::class, 'analyticsVerifiedOrders']);
        // CSR dashboard cards — the signed-in CSR's own figures, off the same
        // POS rollup the analytics cards read, narrowed to the pancake accounts
        // linked to them. Own controller: membership is the gate rather than
        // the analytics permission, and the rows narrow by identity rather than
        // by team. See CsrDashboardController.
        Route::get('/csrs/stats/dashboard-sales', [CsrDashboardController::class, 'sales']);
        Route::get('/csrs/stats/dashboard-rts', [CsrDashboardController::class, 'rts']);
        Route::get('/csrs/stats/dashboard-rmo-called', [CsrDashboardController::class, 'rmoCalled']);
        Route::get('/csrs/stats/dashboard-rmo-time', [CsrDashboardController::class, 'rmoTime']);
        Route::get('/csrs/stats/dashboard-total-rmo-called', [CsrDashboardController::class, 'totalRmoCalled']);
        Route::get('/csrs/stats/dashboard-rmo-call-time', [CsrDashboardController::class, 'rmoCallTime']);
        Route::get('/csrs/stats/dashboard-rmo-real-conversations', [CsrDashboardController::class, 'rmoRealConversations']);
        Route::get('/csrs/stats/dashboard-rmo-hit-rate', [CsrDashboardController::class, 'rmoHitRate']);
        Route::get('/csrs/stats/dashboard-calls-placed', [CsrDashboardController::class, 'callsPlaced']);
        Route::get('/csrs/stats/dashboard-real-conversations', [CsrDashboardController::class, 'realConversations']);
        Route::get('/csrs/stats/dashboard-confirmed-risky-orders', [CsrDashboardController::class, 'confirmedRiskyOrders']);
        Route::get('/csrs/stats/dashboard-verified-orders', [CsrDashboardController::class, 'verifiedOrders']);
        // Effort against results, the CSR's own: day by day off the nightly
        // rollup, and hour by hour off the call log that rollup is built from.
        Route::get('/csrs/stats/dashboard-daily-effort', [CsrDashboardController::class, 'dailyEffort']);
        Route::get('/csrs/stats/dashboard-hourly-effort', [CsrDashboardController::class, 'hourlyEffort']);
        // The CSR's own days, every figure of both rollups — the analytics
        // breakdown's columns at a per-da  y grain instead of per-CSR.
        Route::get('/csrs/stats/dashboard-breakdown', [CsrDashboardController::class, 'breakdown']);
        // Leaders for the period — who came top, same source as the cards above.
        Route::get('/csrs/stats/analytics-leader-sales', [CSRController::class, 'analyticsLeaderSales']);
        Route::get('/csrs/stats/analytics-leader-rts', [CSRController::class, 'analyticsLeaderRts']);
        Route::get('/csrs/stats/analytics-leader-rmo-called', [CSRController::class, 'analyticsLeaderRmoCalled']);
        Route::get('/csrs/stats/analytics-leader-rmo-duration', [CSRController::class, 'analyticsLeaderRmoDuration']);
        // The field behind the leaders: every CSR on one axis, all four
        // metrics in a single response so switching tabs costs nothing.
        Route::get('/csrs/stats/analytics-comparison', [CSRController::class, 'analyticsComparison']);
        // Effort against results, day by day: calls placed beside the ones that
        // turned into a conversation.
        Route::get('/csrs/stats/analytics-daily-effort', [CSRController::class, 'analyticsDailyEffort']);
        // The same effort and results by hour of day, read off the call log
        // itself — the daily rollup has no hour to group by.
        Route::get('/csrs/stats/analytics-hourly-effort', [CSRController::class, 'analyticsHourlyEffort']);
        // The same days as numbers: where every call ended up, and the day's hit rate.

        // Kick the nightly CSR rollups by hand. Everything on the analytics
        // page is built by them, so a gap is closed by re-running one instead
        // of waiting for the schedule. Throttled — each run fans out jobs.
        Route::post('/csrs/sync', [CSRController::class, 'runSync'])
            ->middleware('throttle:6,1')
            ->name('csrs.sync');
        Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/shops', [ShopController::class, 'index'])->name('shops.index');
        Route::get('/pages', [PageController::class, 'index'])->name('pages.index');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/leaderboards', [CsrPerformanceController::class, 'leaderboards'])->name('leaderboards.index');

        // Parcel journey KPIs — one endpoint per stat card so each loads,
        // skeletons and refreshes on its own. See ParcelJourneyStatsController.
        Route::prefix('rts/parcel-journey/kpi')->name('rts.parcel-journey.kpi.')->group(function () {
            Route::get('/tracked-orders', [ParcelJourneyStatsController::class, 'trackedOrders'])->name('tracked-orders');
            Route::get('/sms-sent', [ParcelJourneyStatsController::class, 'smsSent'])->name('sms-sent');
            Route::get('/chat-sent', [ParcelJourneyStatsController::class, 'chatSent'])->name('chat-sent');
            Route::get('/total-sent', [ParcelJourneyStatsController::class, 'totalSent'])->name('total-sent');
        });

        // The per-shop breakdown under the cards — paginated and sorted over
        // XHR, so the page no longer carries it.
        Route::get('/rts/parcel-journey/shops', [ParcelJourneyStatsController::class, 'shops'])
            ->name('rts.parcel-journey.shops');

        // Sales & Marketing dashboard — one endpoint per KPI so each loads,
        // skeletons and retries independently. See SalesMarketingDashboardController.
        Route::prefix('sales-marketing/dashboard/kpi')->name('sales-marketing.dashboard.kpi.')->group(function () {
            Route::get('/total-sales', [SalesMarketingDashboardController::class, 'totalSales'])->name('total-sales');
            Route::get('/total-ad-spend', [SalesMarketingDashboardController::class, 'totalAdSpend'])->name('total-ad-spend');
            Route::get('/blended-roas', [SalesMarketingDashboardController::class, 'blendedRoas'])->name('blended-roas');
            Route::get('/rts-rate', [SalesMarketingDashboardController::class, 'rtsRate'])->name('rts-rate');
        });

        // Per-advertiser figures the team comparison plots. Its own endpoint:
        // the panel switches metric client-side, so one fetch serves all four.
        Route::get('/sales-marketing/dashboard/team-comparison', [SalesMarketingDashboardController::class, 'teamComparison'])
            ->name('sales-marketing.dashboard.team-comparison');
        Route::get('/sales-marketing/dashboard/team-breakdown', [SalesMarketingDashboardController::class, 'teamBreakdown'])
            ->name('sales-marketing.dashboard.team-breakdown');

        // The same window cut by product instead of advertiser, on the same
        // terms — one fetch, every metric derived from it client-side.
        Route::get('/sales-marketing/dashboard/product-comparison', [SalesMarketingDashboardController::class, 'productComparison'])
            ->name('sales-marketing.dashboard.product-comparison');
        Route::get('/sales-marketing/dashboard/product-breakdown', [SalesMarketingDashboardController::class, 'productBreakdown'])
            ->name('sales-marketing.dashboard.product-breakdown');

        // "Leaders for the period" — who topped each figure, on their own
        // endpoints so the section loads independently of the KPI row.
        Route::prefix('sales-marketing/dashboard/leaders')->name('sales-marketing.dashboard.leaders.')->group(function () {
            Route::get('/highest-ad-spend', [SalesMarketingDashboardController::class, 'highestAdSpend'])->name('highest-ad-spend');
            Route::get('/highest-sales', [SalesMarketingDashboardController::class, 'highestSales'])->name('highest-sales');
            Route::get('/highest-roas', [SalesMarketingDashboardController::class, 'highestRoas'])->name('highest-roas');
            Route::get('/lowest-rts', [SalesMarketingDashboardController::class, 'lowestRts'])->name('lowest-rts');
        });

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
