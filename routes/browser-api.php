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
    });
});

Route::group(['prefix' => 'api/v1/workspace', 'as' => 'api.v1.workspace', 'middleware' => ['auth', 'workspace']], function () {
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/breakdown', [AnalyticsController::class, 'breakdown'])->name('analytics.breakdown');
    Route::get('/analytics/per-page', [AnalyticsController::class, 'perPage'])->name('analytics.perPage');
    Route::get('/analytics/per-shop', [AnalyticsController::class, 'perShop'])->name('analytics.perShop');
    Route::get('/analytics/per-user', [AnalyticsController::class, 'perUser'])->name('analytics.perUser');
});
