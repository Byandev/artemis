<?php

use App\Http\Controllers\API\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\API\Workspace\ActivityLogController;
use App\Http\Controllers\API\Workspace\AnalyticsController;
use App\Http\Controllers\API\Workspace\ChecklistController;
use App\Http\Controllers\API\Workspace\CSRController;
use App\Http\Controllers\API\Workspace\CsrDashboardController;
use App\Http\Controllers\API\Workspace\CsrPerformanceController;
use App\Http\Controllers\API\Workspace\DepartmentController;
use App\Http\Controllers\API\Workspace\PageController;
use App\Http\Controllers\API\Workspace\ParcelJourneyStatsController;
use App\Http\Controllers\API\Workspace\RoleController;
use App\Http\Controllers\API\Workspace\SalesMarketingDashboardController;
use App\Http\Controllers\API\Workspace\ShopController;
use App\Http\Controllers\API\Workspace\TeamController;
use App\Http\Controllers\API\Workspace\UserController;
use App\Http\Controllers\API\Workspace\VideoEditorDashboardController;
use App\Http\Controllers\API\Workspace\WelleStatsController;
use Modules\Courses\Http\Controllers\Api\CourseCatalogController;
use Modules\Inventory\Http\Controllers\Api\InventoryDashboardStatsController;
use Modules\Inventory\Http\Controllers\Api\PurchaseOrderFlowController;
use Modules\Products\Http\Controllers\Api\ProductController;
use Modules\TaskManagement\Http\Controllers\Api\CommentController as TaskCommentController;
use Modules\TaskManagement\Http\Controllers\Api\FolderController as TaskFolderController;
use Modules\TaskManagement\Http\Controllers\Api\LabelController as TaskLabelController;
use Modules\TaskManagement\Http\Controllers\Api\SpaceController as TaskSpaceController;
use Modules\TaskManagement\Http\Controllers\Api\SpaceMemberCandidateController as TaskSpaceMemberCandidateController;
use Modules\TaskManagement\Http\Controllers\Api\SpaceMemberController as TaskSpaceMemberController;
use Modules\TaskManagement\Http\Controllers\Api\TaskAttachmentController;
use Modules\TaskManagement\Http\Controllers\Api\TaskController;
use Modules\TaskManagement\Http\Controllers\Api\TaskListController;
use Modules\TaskManagement\Http\Controllers\Api\TaskStatusController;
use Modules\TaskManagement\Http\Middleware\EnsureTaskManagementAccess;

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

        // The departments page's list. Create/edit/delete stay on the Inertia
        // routes in routes/workspaces.php.
        Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');

        // The roles page's list. Create/edit/archive stay on the Inertia
        // routes in routes/workspaces.php.
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');

        // The checklist page's list. Create/edit/delete stay on the Inertia
        // routes in routes/workspaces.php.
        Route::get('/checklist', [ChecklistController::class, 'index'])->name('checklist.index');
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

        // Courses page — the grid, the stat tiles and the leaderboard load over
        // XHR, each on its own. See CourseCatalogController.
        Route::prefix('courses')->name('courses.')->group(function () {
            Route::get('/', [CourseCatalogController::class, 'courses'])->name('index');
            Route::get('/stats', [CourseCatalogController::class, 'stats'])->name('stats');
            Route::get('/leaderboard', [CourseCatalogController::class, 'leaderboard'])->name('leaderboard');
        });

        // Task Management — the Space > Folder > List > Task API the tasks
        // board reads and writes, ported from Matrix's /api/v1. Records are
        // addressed by id below the workspace prefix; the middleware 404s any
        // that belong to another workspace, and the space-role policies decide
        // the rest.
        Route::prefix('task-management')
            ->name('task-management.')
            ->middleware(EnsureTaskManagementAccess::class)
            ->group(function () {
                // `missing` answers an unknown id exactly as a policy denial
                // does, so the response never names the model or the id.
                $notFound = fn () => abort(404, 'Not Found');

                Route::get('spaces', [TaskSpaceController::class, 'index'])->name('spaces.index');
                Route::post('spaces', [TaskSpaceController::class, 'store'])->name('spaces.store');
                Route::get('spaces/{space}', [TaskSpaceController::class, 'show'])->missing($notFound)->name('spaces.show');
                Route::match(['put', 'patch'], 'spaces/{space}', [TaskSpaceController::class, 'update'])->missing($notFound)->name('spaces.update');
                Route::delete('spaces/{space}', [TaskSpaceController::class, 'destroy'])->missing($notFound)->name('spaces.destroy');

                Route::get('spaces/{space}/folders', [TaskFolderController::class, 'index'])->missing($notFound)->name('spaces.folders.index');
                Route::post('spaces/{space}/folders', [TaskFolderController::class, 'store'])->missing($notFound)->name('spaces.folders.store');
                Route::get('folders/{folder}', [TaskFolderController::class, 'show'])->missing($notFound)->name('folders.show');
                Route::match(['put', 'patch'], 'folders/{folder}', [TaskFolderController::class, 'update'])->missing($notFound)->name('folders.update');
                Route::delete('folders/{folder}', [TaskFolderController::class, 'destroy'])->missing($notFound)->name('folders.destroy');

                Route::get('spaces/{space}/lists', [TaskListController::class, 'index'])->missing($notFound)->name('spaces.lists.index');
                Route::post('spaces/{space}/lists', [TaskListController::class, 'store'])->missing($notFound)->name('spaces.lists.store');
                Route::get('lists/{list}', [TaskListController::class, 'show'])->missing($notFound)->name('lists.show');
                Route::match(['put', 'patch'], 'lists/{list}', [TaskListController::class, 'update'])->missing($notFound)->name('lists.update');
                Route::delete('lists/{list}', [TaskListController::class, 'destroy'])->missing($notFound)->name('lists.destroy');

                Route::get('spaces/{space}/member-candidates', [TaskSpaceMemberCandidateController::class, 'index'])->missing($notFound)->name('spaces.member-candidates.index');
                Route::get('spaces/{space}/members', [TaskSpaceMemberController::class, 'index'])->missing($notFound)->name('spaces.members.index');
                Route::post('spaces/{space}/members', [TaskSpaceMemberController::class, 'store'])->missing($notFound)->name('spaces.members.store');
                Route::patch('spaces/{space}/members/{user}', [TaskSpaceMemberController::class, 'update'])->missing($notFound)->name('spaces.members.update');
                Route::delete('spaces/{space}/members/{user}', [TaskSpaceMemberController::class, 'destroy'])->missing($notFound)->name('spaces.members.destroy');

                Route::get('spaces/{space}/statuses', [TaskStatusController::class, 'index'])->missing($notFound)->name('spaces.statuses.index');
                Route::post('spaces/{space}/statuses', [TaskStatusController::class, 'store'])->missing($notFound)->name('spaces.statuses.store');
                Route::match(['put', 'patch'], 'statuses/{status}', [TaskStatusController::class, 'update'])->missing($notFound)->name('statuses.update');
                Route::delete('statuses/{status}', [TaskStatusController::class, 'destroy'])->missing($notFound)->name('statuses.destroy');

                Route::get('spaces/{space}/labels', [TaskLabelController::class, 'index'])->missing($notFound)->name('spaces.labels.index');
                Route::post('spaces/{space}/labels', [TaskLabelController::class, 'store'])->missing($notFound)->name('spaces.labels.store');
                Route::match(['put', 'patch'], 'labels/{label}', [TaskLabelController::class, 'update'])->missing($notFound)->name('labels.update');
                Route::delete('labels/{label}', [TaskLabelController::class, 'destroy'])->missing($notFound)->name('labels.destroy');

                // Tasks can be read across every visible space in the
                // workspace or scoped to a single list.
                Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
                Route::get('lists/{list}/tasks', [TaskController::class, 'indexForList'])->missing($notFound)->name('lists.tasks.index');
                Route::post('lists/{list}/tasks', [TaskController::class, 'store'])->missing($notFound)->name('lists.tasks.store');
                Route::get('tasks/{task}', [TaskController::class, 'show'])->missing($notFound)->name('tasks.show');
                Route::match(['put', 'patch'], 'tasks/{task}', [TaskController::class, 'update'])->missing($notFound)->name('tasks.update');
                Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->missing($notFound)->name('tasks.destroy');

                Route::get('tasks/{task}/comments', [TaskCommentController::class, 'index'])->missing($notFound)->name('tasks.comments.index');
                Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])->missing($notFound)->name('tasks.comments.store');
                Route::match(['put', 'patch'], 'comments/{comment}', [TaskCommentController::class, 'update'])->missing($notFound)->name('comments.update');
                Route::delete('comments/{comment}', [TaskCommentController::class, 'destroy'])->missing($notFound)->name('comments.destroy');

                // `download` and `preview` are plain GETs a browser follows as
                // a link; the disk is private, so every fetch is authorized and
                // the file streamed back from here.
                Route::get('tasks/{task}/attachments', [TaskAttachmentController::class, 'index'])->missing($notFound)->name('tasks.attachments.index');
                Route::post('tasks/{task}/attachments', [TaskAttachmentController::class, 'store'])->missing($notFound)->name('tasks.attachments.store');
                Route::get('attachments/{attachment}/download', [TaskAttachmentController::class, 'download'])->missing($notFound)->name('attachments.download');
                Route::get('attachments/{attachment}/preview', [TaskAttachmentController::class, 'preview'])->missing($notFound)->name('attachments.preview');
                Route::delete('attachments/{attachment}', [TaskAttachmentController::class, 'destroy'])->missing($notFound)->name('attachments.destroy');
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

        // My ESC — the signed-in user's own Welle figures, one endpoint per
        // card so each skeletons on its own. Behind the workspace's Welle
        // module toggle and the "View My ESC" grant, both re-checked in the
        // controller. See WelleStatsController.
        Route::prefix('welle/stats')->name('welle.stats.')->group(function () {
            Route::get('/esc-rate', [WelleStatsController::class, 'escRate'])->name('esc-rate');
            Route::get('/movement', [WelleStatsController::class, 'movement'])->name('movement');
            Route::get('/meditation', [WelleStatsController::class, 'meditation'])->name('meditation');
            Route::get('/learning', [WelleStatsController::class, 'learning'])->name('learning');
            Route::get('/pillar-breakdown', [WelleStatsController::class, 'pillarBreakdown'])->name('pillar-breakdown');
            Route::get('/calendar', [WelleStatsController::class, 'calendar'])->name('calendar');
            Route::get('/daily-log', [WelleStatsController::class, 'dailyLog'])->name('daily-log');
        });

        // Workspace activity log (audit trail) — the table and the stat cards
        // load separately. Gated to workspace admins in the controller.
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('/activity-logs/summary', [ActivityLogController::class, 'summary'])->name('activity-logs.summary');
    });

    // Global, cross-workspace activity log. Super admins only, checked in the controller.
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/activity-logs', [AdminActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('/activity-logs/summary', [AdminActivityLogController::class, 'summary'])->name('activity-logs.summary');
    });
});

Route::group(['prefix' => 'api/v1/workspace', 'as' => 'api.v1.workspace', 'middleware' => ['auth', 'workspace']], function () {
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/breakdown', [AnalyticsController::class, 'breakdown'])->name('analytics.breakdown');
    Route::get('/analytics/per-page', [AnalyticsController::class, 'perPage'])->name('analytics.perPage');
    Route::get('/analytics/per-shop', [AnalyticsController::class, 'perShop'])->name('analytics.perShop');
    Route::get('/analytics/per-user', [AnalyticsController::class, 'perUser'])->name('analytics.perUser');
});
