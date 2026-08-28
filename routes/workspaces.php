<?php

use App\Http\Controllers\Admin\AdminActivityLogController;
use App\Http\Controllers\Admin\AdminClientReportController;
use App\Http\Controllers\Admin\AdminInvoiceController;
use App\Http\Controllers\Admin\AdminSubscriptionPlanController;
use App\Http\Controllers\Admin\AdminSupportTicketController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminWorkspaceController;
use App\Http\Controllers\PublicSalesTargetController;
use App\Http\Controllers\Workspaces\ActivityLogController;
use App\Http\Controllers\Workspaces\Admin\MetricSettingController;
use App\Http\Controllers\Workspaces\AskDataController;
use App\Http\Controllers\Workspaces\ChecklistController;
use App\Http\Controllers\Workspaces\ChecklistProgressController;
use App\Http\Controllers\Workspaces\CSRController;
use App\Http\Controllers\Workspaces\DepartmentController;
use App\Http\Controllers\Workspaces\OnboardingController;
use App\Http\Controllers\Workspaces\PageController;
use App\Http\Controllers\Workspaces\PageDailyBudgetRecordController;
use App\Http\Controllers\Workspaces\PageRoasTrackerController;
use App\Http\Controllers\Workspaces\Product\AnalyticsController;
use App\Http\Controllers\Workspaces\ProductController;
use App\Http\Controllers\Workspaces\RoleController;
use App\Http\Controllers\Workspaces\RolePermissionController;
use App\Http\Controllers\Workspaces\RTS\AnalyticController;
use App\Http\Controllers\Workspaces\RTS\ForDeliveryController;
use App\Http\Controllers\Workspaces\RTS\ParcelUpdateNotificationController;
use App\Http\Controllers\Workspaces\RTS\ParcelUpdateNotificationTemplateController;
use App\Http\Controllers\Workspaces\SalesMarketingDashboardController;
use App\Http\Controllers\Workspaces\SalesTargetController;
use App\Http\Controllers\Workspaces\ShopController;
use App\Http\Controllers\Workspaces\SupportTicketController;
use App\Http\Controllers\Workspaces\TeamAdAccountController;
use App\Http\Controllers\Workspaces\TeamAdSpendGoalController;
use App\Http\Controllers\Workspaces\TeamController;
use App\Http\Controllers\Workspaces\TeamScheduleController;
use App\Http\Controllers\Workspaces\TeamShopController;
use App\Http\Controllers\Workspaces\VideoEditorDashboardController;
use App\Http\Controllers\Workspaces\WorkspaceApiKeyController;
use App\Http\Controllers\Workspaces\WorkspaceController;
use App\Http\Controllers\Workspaces\WorkspaceInvitationController;
use App\Http\Controllers\Workspaces\WorkspaceMemberController;
use App\Http\Controllers\Workspaces\WorkspaceSetupController;
use App\Models\Workspace;
use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\InvoiceController as BillingInvoiceController;
use Modules\Botcake\Http\Controllers\Web\FlowController;
use Modules\Botcake\Http\Controllers\Web\SequenceController;
use Modules\Botcake\Http\Controllers\Web\SequenceMessageController;
use Modules\Courses\Http\Controllers\CourseLessonController;
use Modules\Courses\Http\Controllers\CourseModuleController;
use Modules\Courses\Http\Controllers\CourseProgressController;
use Modules\Courses\Http\Controllers\CoursesController;
use Modules\Creatives\Http\Controllers\CreativesController;
use Modules\Finance\Http\Controllers\AccountController as FinanceAccountController;
use Modules\Finance\Http\Controllers\DashboardController as FinanceDashboardController;
use Modules\Finance\Http\Controllers\ExpensesController as FinanceExpensesController;
use Modules\Finance\Http\Controllers\FundRequestController as FinanceFundRequestController;
use Modules\Finance\Http\Controllers\IncomeStatementController as FinanceIncomeStatementController;
use Modules\Finance\Http\Controllers\RemittanceController as FinanceRemittanceController;
use Modules\Finance\Http\Controllers\TransactionController as FinanceTransactionController;
use Modules\Finance\Http\Controllers\TransactionTypeController as FinanceTransactionTypeController;
use Modules\Finance\Http\Controllers\UserIncomeStatementController as FinanceUserIncomeStatementController;
use Modules\GencysERP\Http\Controllers\Web\DailySalesTrackerController as GencysDailySalesTrackerController;
use Modules\GencysERP\Http\Controllers\Web\InternController as GencysInternController;
use Modules\GencysERP\Http\Controllers\Web\PageController as GencysPageController;
use Modules\GencysERP\Http\Controllers\Web\SyncBatchController as GencysSyncBatchController;
use Modules\GencysERP\Http\Controllers\Web\SyncRunController as GencysSyncRunController;
use Modules\Inventory\Http\Controllers\InventoryDashboardController;
use Modules\Inventory\Http\Controllers\InventoryItemController;
use Modules\Inventory\Http\Controllers\InventoryTransactionController;
use Modules\Inventory\Http\Controllers\PurchasedOrderController;
use Modules\Inventory\Http\Controllers\PurchaseOrderMonitoringController;
use Modules\Inventory\Http\Controllers\UnitCodeController;
use Modules\MetaAds\Http\Controllers\AdAccountOwnerController;
use Modules\MetaAds\Http\Controllers\AdAccountSyncController;
use Modules\MetaAds\Http\Controllers\AdAccountToggleSyncController;
use Modules\MetaAds\Http\Controllers\AdCreatorController;
use Modules\MetaAds\Http\Controllers\AdsCalendarController;
use Modules\MetaAds\Http\Controllers\AdsManagerController;
use Modules\MetaAds\Http\Controllers\AdSpentSummaryController;
use Modules\MetaAds\Http\Controllers\BudgetTrackerController;
use Modules\MetaAds\Http\Controllers\CustomBreakdownController;
use Modules\MetaAds\Http\Controllers\IntegrationsController;
use Modules\MetaAds\Http\Controllers\MetaOAuthController;
use Modules\MetaAds\Http\Controllers\OptimizationRuleController;
use Modules\MetaAds\Http\Controllers\RemoveFbAccountController;
use Modules\MetaAds\Http\Controllers\ReportController;
use Modules\MetaAds\Http\Controllers\SyncHealthController;
use Modules\Pancake\Http\Controllers\CourierShipmentController;
use Modules\Pancake\Http\Controllers\OrderController;
use Modules\SimGateway\Http\Controllers\Admin\AdminSimController;
use Modules\SimGateway\Http\Controllers\SmsController;

/*
|--------------------------------------------------------------------------
| Workspace Routes
|--------------------------------------------------------------------------
|
| These routes handle workspace management, including CRUD operations,
| member management, and invitation handling.
|
*/
Route::get('/public/workspaces/{workspace}/sales-targets', [PublicSalesTargetController::class, 'index'])->name('public-page.sales-targets');
Route::post('/public/workspaces/{workspace}/sales-targets/verify-password', [PublicSalesTargetController::class, 'verifyPublicPassword'])->name('public-page.sales-targets.verify-password');
// The board only fetches what it cannot work out for itself: the day's measured
// totals, each team's goal and actual, and the leader's sparkline. Percentages,
// ranking, bands and the leaderboard slice are derived client-side.
Route::get('/public/workspaces/{workspace}/sales-targets/kpis', [PublicSalesTargetController::class, 'kpis'])->name('public-page.sales-targets.kpis');
Route::get('/public/workspaces/{workspace}/sales-targets/teams', [PublicSalesTargetController::class, 'teams'])->name('public-page.sales-targets.teams');
Route::get('/public/workspaces/{workspace}/sales-targets/team-trend', [PublicSalesTargetController::class, 'teamTrend'])->name('public-page.sales-targets.team-trend');
// Last, and digits only, so the literal section paths above always win.
Route::get('/public/workspaces/{workspace}/sales-targets/{salesTarget}', [PublicSalesTargetController::class, 'index'])
    ->whereNumber('salesTarget')
    ->name('public-page.sales-targets.show');

Route::get('/public/workspaces/{workspace}/rts/rmo-management', [ForDeliveryController::class, 'public'])->name('public-page.rmo-management');
Route::get('/public/workspaces/{workspace}/rts/rmo-management/stats', [ForDeliveryController::class, 'publicStats'])->name('public-page.rmo-management.stats');
Route::get('/public/workspaces/{workspace}/rts/rmo-management/export', [ForDeliveryController::class, 'publicExport'])->name('public-page.rmo-management.export');
Route::post('/public/workspaces/{workspace}/rts/rmo-management/verify-password', [ForDeliveryController::class, 'verifyPublicPassword'])->name('public-page.rmo-management.verify-password');
Route::post('/public/workspaces/{workspace}/rts/rmo-management/bulk-assign', [ForDeliveryController::class, 'publicBulkAssign'])->name('public-page.rmo-management.bulkAssign');
Route::post('/public/workspaces/{workspace}/rts/rmo-management/bulk-status', [ForDeliveryController::class, 'publicBulkUpdateStatus'])->name('public-page.rmo-management.bulkUpdateStatus');
Route::post('/public/workspaces/{workspace}/rts/rmo-management/{id}', [ForDeliveryController::class, 'publicUpdateStatus'])->name('public-page.rmo-management.updateStatus');
Route::post('/public/workspaces/{workspace}/rts/rmo-management/{id}/assign', [ForDeliveryController::class, 'publicAssignUser'])->name('public-page.rmo-management.assign');
Route::post('/public/workspaces/{workspace}/rts/rmo-management/{id}/remove-assignee', [ForDeliveryController::class, 'publicRemoveAssignee'])->name('public-page.rmo-management.removeAssignee');
Route::post('/public/workspaces/{workspace}/rts/rmo-management/{id}/update-phones', [ForDeliveryController::class, 'publicUpdatePhones'])->name('public-page.rmo-management.updatePhones');
Route::get('/public/workspaces/{workspace}/rts/rmo-management/call-logs', [ForDeliveryController::class, 'callLogs'])->name('public-page.rmo-management.callLogs');
Route::get('/public/workspaces/{workspace}/rts/rmo-management/call-logs/export', [ForDeliveryController::class, 'publicExportCallLogs'])->name('public-page.rmo-management.callLogs.export');
Route::get('/public/workspaces/{workspace}/rts/rmo-management/call-logs/breakdown', [ForDeliveryController::class, 'callLogsBreakdown'])->name('public-page.rmo-management.callLogs.breakdown');

Route::middleware(['auth'])->group(function () {
    // Workspace setup (first-time after registration)
    Route::get('/workspaces/setup', [WorkspaceSetupController::class, 'create'])->name('workspaces.setup');
    Route::post('/workspaces/setup', [WorkspaceSetupController::class, 'store'])->name('workspaces.setup.store');

    Route::prefix('workspaces/{workspace:slug}')->group(function () {
        // Onboarding (after workspace creation)
        Route::get('/onboarding', [OnboardingController::class, 'create'])->name('workspace.onboarding');
        Route::post('/onboarding', [OnboardingController::class, 'store'])->name('workspace.onboarding.store');
        Route::post('/onboarding/skip', [OnboardingController::class, 'skip'])->name('workspace.onboarding.skip');
        Route::get('/onboarding/status', [OnboardingController::class, 'status'])->name('workspace.onboarding.status');

        Route::get('/inventory/transactions', [InventoryTransactionController::class, 'index'])->name('inventory.transactions.index');
        Route::get('/inventory/transactions/last-remaining', [InventoryTransactionController::class, 'lastRemaining'])->name('inventory.transactions.last-remaining');
        Route::post('/inventory/transactions', [InventoryTransactionController::class, 'store'])->name('inventory.transactions.store');
        Route::patch('/inventory/transactions/{transaction}', [InventoryTransactionController::class, 'update'])->name('inventory.transactions.update');
        Route::delete('/inventory/transactions/{transaction}', [InventoryTransactionController::class, 'destroy'])->name('inventory.transactions.destroy');
    });

    // AI
    Route::post('/workspaces/{workspace}/ask', AskDataController::class)->name('workspace.ask');

    // Workspace dashboard
    Route::get('/workspaces/{workspace}/dashboard', [WorkspaceController::class, 'dashboard'])->name('workspace.dashboard');
    Route::get('/workspaces/{workspace}/chart-data', [WorkspaceController::class, 'getChartData'])->name('workspace.chart-data');

    // Workspace activity log (audit trail) — gated to workspace admins in the controller.
    Route::get('/workspaces/{workspace}/activity-logs', [ActivityLogController::class, 'index'])->name('workspace.activity-logs.index');

    // Role-specific dashboards (scaffold — gated by granular permissions)
    // The S&M dashboard is tabbed; each tab is its own URL segment. The JSON
    // `data` route is registered before the `{tab?}` route so it isn't captured
    // as a tab. Daily Report is the default (bare dashboard path).
    Route::get('/workspaces/{workspace}/sales-marketing/dashboard/data', [SalesMarketingDashboardController::class, 'data'])->name('workspaces.sales-marketing.dashboard.data');
    // Page ROAS Tracker and Ad Spend Goals are dashboard tabs — their own URLs,
    // registered before the {tab?} catch-all so they aren't swallowed by it.
    Route::get('/workspaces/{workspace}/sales-marketing/dashboard/page-roas-tracker', [PageRoasTrackerController::class, 'index'])->name('workspaces.sales-marketing.dashboard.page-roas-tracker');
    Route::get('/workspaces/{workspace}/sales-marketing/dashboard/ad-spend-goals', [TeamAdSpendGoalController::class, 'index'])->name('workspaces.sales-marketing.dashboard.ad-spend-goals');
    Route::get('/workspaces/{workspace}/sales-marketing/dashboard/ad-spent-summary', [AdSpentSummaryController::class, 'index'])->name('workspaces.sales-marketing.dashboard.ad-spent-summary');
    Route::get('/workspaces/{workspace}/sales-marketing/dashboard/sales-targets', [SalesTargetController::class, 'index'])->name('workspaces.sales-marketing.dashboard.sales-targets');
    Route::post('/workspaces/{workspace}/sales-marketing/dashboard/sales-targets', [SalesTargetController::class, 'store'])->name('workspaces.sales-marketing.dashboard.sales-targets.store');
    Route::get('/workspaces/{workspace}/sales-marketing/dashboard/sales-targets/{salesTarget}', [SalesTargetController::class, 'show'])->name('workspaces.sales-marketing.dashboard.sales-targets.show');
    Route::put('/workspaces/{workspace}/sales-marketing/dashboard/sales-targets/{salesTarget}', [SalesTargetController::class, 'update'])->name('workspaces.sales-marketing.dashboard.sales-targets.update');
    Route::delete('/workspaces/{workspace}/sales-marketing/dashboard/sales-targets/{salesTarget}', [SalesTargetController::class, 'destroy'])->name('workspaces.sales-marketing.dashboard.sales-targets.destroy');
    Route::get('/workspaces/{workspace}/sales-marketing/dashboard/{tab?}', [SalesMarketingDashboardController::class, 'index'])->name('workspaces.sales-marketing.dashboard');
    Route::get('/workspaces/{workspace}/video-editor/dashboard', VideoEditorDashboardController::class)->name('workspaces.video-editor.dashboard');

    // Workspace CRUD routes
    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::get('/workspaces/create', [WorkspaceController::class, 'create'])->name('workspaces.create');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::get('/workspaces/{workspace}', [WorkspaceController::class, 'show'])->name('workspaces.show');
    Route::get('/workspaces/{workspace}/edit', [WorkspaceController::class, 'edit'])->name('workspaces.edit');
    Route::put('/workspaces/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::delete('/workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');

    // Workspace switching
    Route::post('/workspaces/{workspace}/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');

    // Public-pages access password (gates public RMO management & leaderboard)
    Route::post('/workspaces/{workspace}/public-password', [WorkspaceController::class, 'updatePublicPassword'])->name('workspaces.public-password.update');

    // Member management routes
    Route::get('/workspaces/{workspace}/members', [WorkspaceMemberController::class, 'index'])->name('workspaces.members.index');
    //    Route::put('/workspaces/{workspace}/members/{user}', [WorkspaceMemberController::class, 'update'])->name('workspaces.members.update');
    Route::put('/workspaces/{workspace:slug}/members/{user}', [WorkspaceMemberController::class, 'updateMember'])->name('workspaces.members.update');
    Route::put('/workspaces/{workspace}/members/bulk/department', [WorkspaceMemberController::class, 'bulkAssignDepartment'])->name('workspaces.members.department.bulk');
    Route::put('/workspaces/{workspace}/members/{user}/department', [WorkspaceMemberController::class, 'assignDepartment'])->name('workspaces.members.department.assign');
    Route::delete('/workspaces/{workspace}/members/{user}', [WorkspaceMemberController::class, 'destroy'])->name('workspaces.members.destroy');
    Route::post('/workspaces/{workspace}/members/{user}/reset-password', [WorkspaceMemberController::class, 'generatePasswordReset'])->name('workspaces.members.reset-password');

    // API Keys
    Route::get('/workspaces/{workspace}/api-keys', [WorkspaceApiKeyController::class, 'index'])->name('workspaces.api-keys.index');
    Route::post('/workspaces/{workspace}/api-keys', [WorkspaceApiKeyController::class, 'store'])->name('workspaces.api-keys.store');
    Route::get('/workspaces/{workspace}/api-keys/{apiKey}/reveal', [WorkspaceApiKeyController::class, 'reveal'])->name('workspaces.api-keys.reveal');
    Route::delete('/workspaces/{workspace}/api-keys/{apiKey}', [WorkspaceApiKeyController::class, 'destroy'])->name('workspaces.api-keys.destroy');

    // Invitation routes (authenticated users)
    Route::post('/workspaces/{workspace}/invitations', [WorkspaceInvitationController::class, 'store'])->name('workspaces.invitations.store');
    Route::post('/workspaces/invitations/{invitation}/resend', [WorkspaceInvitationController::class, 'resend'])->name('workspaces.invitations.resend');
    Route::delete('/workspaces/invitations/{invitation}', [WorkspaceInvitationController::class, 'destroy'])->name('workspaces.invitations.destroy');

    //    Route::get('/workspaces/{workspace}/products', [ProductController::class, 'index'])->name('workspaces.products.index');
    //    Route::get('/workspaces/{workspace}/products/create', [ProductController::class, 'create'])->name('workspaces.products.create');
    //    Route::post('/workspaces/{workspace}/products', [ProductController::class, 'store'])->name('workspaces.products.store');

    Route::get('/workspaces/{workspace}/pages', [PageController::class, 'index'])->name('workspaces.pages.index');
    Route::get('/workspaces/{workspace}/pages/export', [PageController::class, 'export'])->name('workspaces.pages.export');
    Route::post('/workspaces/{workspace}/pages/import', [PageController::class, 'import'])->name('workspaces.pages.import');
    Route::post('/workspaces/{workspace}/pages/validate-pancake-token', [PageController::class, 'validatePancakeToken'])->name('workspaces.pages.validate-pancake-token');
    Route::post('/workspaces/{workspace}/pages/validate-botcake-token', [PageController::class, 'validateBotcakeToken'])->name('workspaces.pages.validate-botcake-token');
    Route::post('/workspaces/{workspace}/pages/validate-flow-id', [PageController::class, 'validateFlowId'])->name('workspaces.pages.validate-flow-id');
    Route::post('/workspaces/{workspace}/pages/validate-custom-field-id', [PageController::class, 'validateCustomFieldId'])->name('workspaces.pages.validate-custom-field-id');
    Route::get('/workspaces/{workspace}/pages/{page}/edit', [PageController::class, 'edit'])->name('workspaces.pages.edit');
    Route::put('/workspaces/{workspace}/pages/{page}', [PageController::class, 'update'])->name('workspaces.pages.update');
    Route::put('/workspaces/{workspace}/pages/{page}/budget', [PageController::class, 'updateBudget'])->name('workspaces.pages.update-budget');
    Route::patch('/workspaces/{workspace}/pages/{page}/assign-owner', [PageController::class, 'assignOwner'])->name('workspaces.pages.assign-owner');
    Route::patch('/workspaces/{workspace}/pages/{page}/auto-budget', [PageController::class, 'updateAutoBudget'])->name('workspaces.pages.auto-budget');
    Route::post('/workspaces/{workspace}/pages/{page}/archive', [PageController::class, 'archive'])->name('workspaces.pages.archive');
    Route::post('/workspaces/{workspace}/pages/{page}/restore', [PageController::class, 'restore'])->name('workspaces.pages.restore');

    // Page Daily Budget Records
    Route::get('/workspaces/{workspace}/page-daily-budget-records', [PageDailyBudgetRecordController::class, 'index'])->name('workspaces.page-daily-budget-records.index');
    Route::post('/workspaces/{workspace}/page-daily-budget-records', [PageDailyBudgetRecordController::class, 'store'])->name('workspaces.page-daily-budget-records.store');
    Route::put('/workspaces/{workspace}/page-daily-budget-records/{pageDailyBudgetRecord}', [PageDailyBudgetRecordController::class, 'update'])->name('workspaces.page-daily-budget-records.update');
    Route::delete('/workspaces/{workspace}/page-daily-budget-records/{pageDailyBudgetRecord}', [PageDailyBudgetRecordController::class, 'destroy'])->name('workspaces.page-daily-budget-records.destroy');

    Route::get('/workspaces/{workspace}/shops', [ShopController::class, 'index'])->name('workspaces.shops.index');
    Route::post('/workspaces/{workspace}/shops', [ShopController::class, 'store'])->name('workspaces.shops.store');
    Route::put('/workspaces/{workspace}/shops/{shop}', [ShopController::class, 'update'])->name('workspaces.shops.update');
    Route::post('/workspaces/{workspace}/shops/validate-pos-token', [ShopController::class, 'validatePosToken'])->name('workspaces.shops.validate-pos-token');
    Route::post('/workspaces/{workspace}/shops/{shop}/refresh-pages', [ShopController::class, 'refreshPages'])->name('workspaces.shops.refresh-pages');
    Route::post('/workspaces/{workspace}/shops/{shop}/refresh-users', [ShopController::class, 'refreshUsers'])->name('workspaces.shops.refresh-users');
    Route::post('/workspaces/{workspace}/shops/{shop}/refresh-orders', [ShopController::class, 'refreshOrders'])->name('workspaces.shops.refresh-orders');
    Route::delete('/workspaces/{workspace}/shops/{shop}', [ShopController::class, 'destroy'])->name('workspaces.shops.destroy');

    // Moved to the S&M dashboard's "Page ROAS Tracker" tab — keep the old URL
    // working by redirecting to the new tab route.
    Route::get('/workspaces/{workspace}/page-roas-tracker', fn (Workspace $workspace) => redirect()->route('workspaces.sales-marketing.dashboard.page-roas-tracker', $workspace))->name('workspaces.page-roas-tracker.index');

    // Product routes
    // Redirect to analytics by default for navigation item active state
    Route::get('/workspaces/{workspace}/products', function (Workspace $workspace) {
        return redirect()->route('workspaces.products.analytics', $workspace);
    })->name('workspaces.products');
    Route::get('/workspaces/{workspace}/products/list', [ProductController::class, 'index'])->name('workspaces.products.index');
    Route::get('/workspaces/{workspace}/products/analytics', [AnalyticsController::class, 'index'])->name('workspaces.products.analytics');
    Route::get('/workspaces/{workspace}/products/analytics/metrics', [AnalyticsController::class, 'metrics'])->name('workspaces-workspace.products.analytics.metrics');
    Route::get('/workspaces/{workspace}/products/create', [ProductController::class, 'create'])->name('workspaces.products.create');
    Route::post('/workspaces/{workspace}/products', [ProductController::class, 'store'])->name('workspaces.products.store');
    Route::get('/workspaces/{workspace}/products/{product}/edit', [ProductController::class, 'edit'])->name('workspaces.products.edit');
    Route::put('/workspaces/{workspace}/products/{product}', [ProductController::class, 'update'])->name('workspaces.products.update');
    Route::delete('/workspaces/{workspace}/products/{product}', [ProductController::class, 'destroy'])->name('workspaces.products.destroy');

    // RTS routes
    // Redirect to analytics by default for navigation item active state
    Route::get('/workspaces/{workspace}/rts', function (Workspace $workspace) {
        return redirect()->route('workspaces.rts.analytics', $workspace);
    })->name('workspaces.rts');
    Route::get('/workspaces/{workspace}/rts/analytics', [AnalyticController::class, 'index'])->name('workspaces.rts.analytics');
    Route::get('/workspaces/{workspace}/rts/analytics/group-by/order-item', [AnalyticController::class, 'groupByOrderItem'])->name('workspaces.rts.analytics.group-by-order-item');
    Route::get('/workspaces/{workspace}/rts/analytics/group-by/price', [AnalyticController::class, 'groupByPrice'])->name('workspaces.rts.analytics.group-by-price');
    Route::get('/workspaces/{workspace}/rts/analytics/group-by/cx-rts', [AnalyticController::class, 'groupByCxRts'])->name('workspaces.rts.analytics.group-by-cx-rts');
    Route::get('/workspaces/{workspace}/rts/analytics/group-by/delivery-attempts', [AnalyticController::class, 'groupByDeliveryAttempts'])->name('workspaces.rts.analytics.group-by-delivery-attempts');
    Route::get('/workspaces/{workspace}/rts/analytics/group-by/order-source', [AnalyticController::class, 'groupByOrderSource'])->name('workspaces.rts.analytics.group-by-order-source');
    Route::get('/workspaces/{workspace}/rts/analytics/group-by/ad', [AnalyticController::class, 'groupByAd'])->name('workspaces.rts.analytics.group-by-ad');
    Route::get('/workspaces/{workspace}/rts/analytics/group-by/order-frequency', [AnalyticController::class, 'groupByOrderFrequency'])->name('workspaces.rts.analytics.group-by-order-frequency');
    Route::get('/workspaces/{workspace}/rts/analytics/group-by/confirmed-by', [AnalyticController::class, 'groupByConfirmedBy'])->name('workspaces.rts.analytics.group-by-confirmed-by');
    Route::get('/workspaces/{workspace}/rts/analytics/group-by/rider', [AnalyticController::class, 'groupByRider'])->name('workspaces.rts.analytics.group-by-rider');
    Route::get('/workspaces/{workspace}/rts/analytics/group-by/provinces', [AnalyticController::class, 'groupByProvinces'])->name('workspaces.rts.analytics.group-by-provinces');
    Route::get('/workspaces/{workspace}/rts/analytics/group-by/cities', [AnalyticController::class, 'groupByCities'])->name('workspaces.rts.analytics.group-by-cities');
    Route::get('/workspaces/{workspace}/rts/parcel-journeys', [ParcelUpdateNotificationTemplateController::class, 'index'])->name('workspaces.rts.parcel-journeys');
    Route::get('/workspaces/{workspace}/rts/parcel-update-notification', [ParcelUpdateNotificationController::class, 'index'])->name('workspaces.rts.parcel-update-notification');
    Route::get('/workspaces/{workspace}/rts/parcel-journey-notification-templates', [ParcelUpdateNotificationTemplateController::class, 'index'])->name('workspaces.rts.parcel-journey-notification-templates.index');
    Route::put('/workspaces/{workspace}/rts/parcel-journey-notification-templates/{template}', [ParcelUpdateNotificationTemplateController::class, 'update'])->name('workspaces.rts.parcel-journey-notification-templates.update');

    Route::get('/workspaces/{workspace}/integrations/meta', [IntegrationsController::class, 'fbAccounts'])
        ->middleware('can:View Meta Ads,workspace')
        ->name('workspaces.metaads.fb-accounts');
    Route::get('/workspaces/{workspace}/integrations/meta/ad-accounts', [IntegrationsController::class, 'adAccounts'])
        ->middleware('can:View Ad Accounts,workspace')
        ->name('workspaces.metaads.ad-accounts');
    Route::get('/workspaces/{workspace}/integrations/meta/ads-manager', [AdsManagerController::class, 'index'])
        ->middleware('can:View Meta Ads,workspace')
        ->name('workspaces.metaads.ads-manager');
    Route::get('/workspaces/{workspace}/integrations/meta/ads-manager/data', [AdsManagerController::class, 'data'])
        ->middleware('can:View Meta Ads,workspace')
        ->name('workspaces.metaads.ads-manager.data');
    Route::get('/workspaces/{workspace}/integrations/meta/ads-manager/ads/{ad}/preview', [AdsManagerController::class, 'adPreview'])
        ->middleware('can:View Meta Ads,workspace')
        ->name('workspaces.metaads.ads-manager.preview');
    Route::get('/workspaces/{workspace}/integrations/meta/ads-manager/ads/{ad}/detail', [AdsManagerController::class, 'adDetail'])
        ->middleware('can:View Meta Ads,workspace')
        ->name('workspaces.metaads.ads-manager.detail');
    Route::post('/workspaces/{workspace}/integrations/meta/ads-manager/ads/creator/bulk', [AdCreatorController::class, 'bulk'])
        ->middleware('can:Manage Meta Ads Accounts,workspace')
        ->name('workspaces.metaads.ads-manager.ads.creator.bulk');
    Route::patch('/workspaces/{workspace}/integrations/meta/ads-manager/ads/{ad}/creator', [AdCreatorController::class, 'update'])
        ->middleware('can:Manage Meta Ads Accounts,workspace')
        ->name('workspaces.metaads.ads-manager.ads.creator');
    Route::get('/workspaces/{workspace}/integrations/meta/ads-calendar', [AdsCalendarController::class, 'index'])
        ->middleware('can:View Meta Ads,workspace')
        ->name('workspaces.metaads.ads-calendar');
    Route::get('/workspaces/{workspace}/integrations/meta/health', [SyncHealthController::class, 'index'])
        ->middleware('can:View Meta Ads,workspace')
        ->name('workspaces.metaads.health');
    Route::get('/workspaces/{workspace}/integrations/meta/budget-tracker', [BudgetTrackerController::class, 'index'])
        ->middleware('can:View Meta Ads,workspace')
        ->name('workspaces.metaads.budget-tracker');
    // Moved to the S&M dashboard's "Ad Spent Summary" tab — keep the old Meta
    // Ads URL working by redirecting to the new tab route.
    Route::get('/workspaces/{workspace}/integrations/meta/ad-spent-summary', fn (Workspace $workspace) => redirect()->route('workspaces.sales-marketing.dashboard.ad-spent-summary', $workspace))
        ->name('workspaces.metaads.ad-spent-summary');

    // Meta Ads optimization rules
    Route::get('/workspaces/{workspace}/integrations/meta/optimization-rules', [OptimizationRuleController::class, 'index'])
        ->middleware('can:View Optimization Rules,workspace')
        ->name('workspaces.metaads.optimization-rules.index');
    Route::get('/workspaces/{workspace}/integrations/meta/optimization-rules/create', [OptimizationRuleController::class, 'create'])
        ->middleware('can:Manage Optimization Rules,workspace')
        ->name('workspaces.metaads.optimization-rules.create');
    Route::get('/workspaces/{workspace}/integrations/meta/optimization-rules/approvals', [OptimizationRuleController::class, 'approvals'])
        ->middleware('can:Approve Optimization Rules,workspace')
        ->name('workspaces.metaads.optimization-rules.approvals');
    Route::post('/workspaces/{workspace}/integrations/meta/optimization-rules/approvals/bulk-approve', [OptimizationRuleController::class, 'bulkApprove'])
        ->middleware('can:Approve Optimization Rules,workspace')
        ->name('workspaces.metaads.optimization-rules.approvals.bulk-approve');
    Route::post('/workspaces/{workspace}/integrations/meta/optimization-rules/approvals/bulk-reject', [OptimizationRuleController::class, 'bulkReject'])
        ->middleware('can:Approve Optimization Rules,workspace')
        ->name('workspaces.metaads.optimization-rules.approvals.bulk-reject');
    Route::post('/workspaces/{workspace}/integrations/meta/optimization-rules/approvals/{proposal}/approve', [OptimizationRuleController::class, 'approveProposal'])
        ->middleware('can:Approve Optimization Rules,workspace')
        ->name('workspaces.metaads.optimization-rules.approvals.approve');
    Route::post('/workspaces/{workspace}/integrations/meta/optimization-rules/approvals/{proposal}/reject', [OptimizationRuleController::class, 'rejectProposal'])
        ->middleware('can:Approve Optimization Rules,workspace')
        ->name('workspaces.metaads.optimization-rules.approvals.reject');
    Route::get('/workspaces/{workspace}/integrations/meta/optimization-rules/logs', [OptimizationRuleController::class, 'logs'])
        ->middleware('can:View Optimization Logs,workspace')
        ->name('workspaces.metaads.optimization-rules.logs');
    // Viewable by "View Optimization Rules" (read-only) or "Manage Optimization
    // Rules" (editable) — the controller authorizes either; saving stays gated
    // by Manage on the update route below.
    Route::get('/workspaces/{workspace}/integrations/meta/optimization-rules/{optimizationRule}/edit', [OptimizationRuleController::class, 'edit'])
        ->name('workspaces.metaads.optimization-rules.edit');
    Route::post('/workspaces/{workspace}/integrations/meta/optimization-rules', [OptimizationRuleController::class, 'store'])
        ->middleware('can:Manage Optimization Rules,workspace')
        ->name('workspaces.metaads.optimization-rules.store');
    Route::put('/workspaces/{workspace}/integrations/meta/optimization-rules/{optimizationRule}', [OptimizationRuleController::class, 'update'])
        ->middleware('can:Manage Optimization Rules,workspace')
        ->name('workspaces.metaads.optimization-rules.update');
    Route::patch('/workspaces/{workspace}/integrations/meta/optimization-rules/{optimizationRule}/toggle', [OptimizationRuleController::class, 'toggle'])
        ->middleware('can:Manage Optimization Rules,workspace')
        ->name('workspaces.metaads.optimization-rules.toggle');
    Route::post('/workspaces/{workspace}/integrations/meta/optimization-rules/{optimizationRule}/run', [OptimizationRuleController::class, 'runNow'])
        ->middleware('can:Manage Optimization Rules,workspace')
        ->name('workspaces.metaads.optimization-rules.run');
    Route::delete('/workspaces/{workspace}/integrations/meta/optimization-rules/{optimizationRule}', [OptimizationRuleController::class, 'destroy'])
        ->middleware('can:Manage Optimization Rules,workspace')
        ->name('workspaces.metaads.optimization-rules.destroy');
    Route::get('/workspaces/{workspace}/integrations/meta/connect', [MetaOAuthController::class, 'redirect'])
        ->middleware('can:Connect FB Account,workspace')
        ->name('workspaces.metaads.connect');
    Route::post('/workspaces/{workspace}/integrations/meta/users/{metaUser}/sync-ad-accounts', AdAccountSyncController::class)
        ->middleware('can:Manage Meta Ads Accounts,workspace')
        ->name('workspaces.metaads.sync-ad-accounts');
    Route::delete('/workspaces/{workspace}/integrations/meta/users/{metaUser}', RemoveFbAccountController::class)
        ->middleware('can:Manage Meta Ads Accounts,workspace')
        ->name('workspaces.metaads.remove-fb-account');
    Route::patch('/workspaces/{workspace}/integrations/meta/ad-accounts/{adAccount}/toggle-sync', AdAccountToggleSyncController::class)
        ->middleware('can:Manage Meta Ads Accounts,workspace')
        ->name('workspaces.metaads.ad-accounts.toggle-sync');
    Route::patch('/workspaces/{workspace}/integrations/meta/ad-accounts/{adAccount}/owner', AdAccountOwnerController::class)
        ->middleware('can:Manage Meta Ads Accounts,workspace')
        ->name('workspaces.metaads.ad-accounts.owner');

    // Meta Ads saved reports (SuperAds-style report builder)
    Route::get('/workspaces/{workspace}/integrations/meta/reports', [ReportController::class, 'index'])
        ->middleware('can:View Meta Ads,workspace')
        ->name('workspaces.metaads.reports.index');
    Route::post('/workspaces/{workspace}/integrations/meta/reports', [ReportController::class, 'store'])
        ->middleware('can:View Meta Ads,workspace')
        ->name('workspaces.metaads.reports.store');
    Route::get('/workspaces/{workspace}/integrations/meta/reports/{report}', [ReportController::class, 'show'])
        ->middleware('can:View Meta Ads,workspace')
        ->name('workspaces.metaads.reports.show');
    Route::patch('/workspaces/{workspace}/integrations/meta/reports/{report}', [ReportController::class, 'update'])
        ->middleware('can:View Meta Ads,workspace')
        ->name('workspaces.metaads.reports.update');
    Route::delete('/workspaces/{workspace}/integrations/meta/reports/{report}', [ReportController::class, 'destroy'])
        ->middleware('can:View Meta Ads,workspace')
        ->name('workspaces.metaads.reports.destroy');
    Route::post('/workspaces/{workspace}/integrations/meta/reports/{report}/restore', [ReportController::class, 'restore'])
        ->middleware('can:View Meta Ads,workspace')
        ->withTrashed()
        ->name('workspaces.metaads.reports.restore');
    Route::delete('/workspaces/{workspace}/integrations/meta/reports/{report}/force', [ReportController::class, 'forceDestroy'])
        ->middleware('can:View Meta Ads,workspace')
        ->withTrashed()
        ->name('workspaces.metaads.reports.force-destroy');

    // Reusable custom breakdowns (named rule-based ad groups) — JSON CRUD
    Route::get('/workspaces/{workspace}/integrations/meta/custom-breakdowns', [CustomBreakdownController::class, 'index'])
        ->middleware('can:View Meta Ads,workspace')
        ->name('workspaces.metaads.custom-breakdowns.index');
    Route::post('/workspaces/{workspace}/integrations/meta/custom-breakdowns', [CustomBreakdownController::class, 'store'])
        ->middleware('can:View Meta Ads,workspace')
        ->name('workspaces.metaads.custom-breakdowns.store');
    Route::patch('/workspaces/{workspace}/integrations/meta/custom-breakdowns/{customBreakdown}', [CustomBreakdownController::class, 'update'])
        ->middleware('can:View Meta Ads,workspace')
        ->name('workspaces.metaads.custom-breakdowns.update');
    Route::delete('/workspaces/{workspace}/integrations/meta/custom-breakdowns/{customBreakdown}', [CustomBreakdownController::class, 'destroy'])
        ->middleware('can:View Meta Ads,workspace')
        ->name('workspaces.metaads.custom-breakdowns.destroy');

    Route::put('/workspaces/{workspace:slug}/employees/{employee}', [CSRController::class, 'update'])->name('employees.update');
    Route::get('/workspaces/{workspace}/csr/dashboard', [CSRController::class, 'dashboard'])->name('workspaces.csr.dashboard');
    Route::get('/workspaces/{workspace}/csr/management', [CSRController::class, 'index'])->name('workspaces.csr.index');
    Route::get('/workspaces/{workspace}/csr/analytics', [CSRController::class, 'analytics'])->name('workspaces.csr.analytics');

    // CSR RMO Management (authenticated)
    Route::get('/workspaces/{workspace}/csr/rmo-management', [ForDeliveryController::class, 'csrRmoManagement'])->name('workspaces.csr.rmo-management');
    Route::get('/workspaces/{workspace}/csr/rmo-management/export', [ForDeliveryController::class, 'publicExport'])->name('workspaces.csr.rmo-management.export');
    Route::post('/workspaces/{workspace}/csr/rmo-management/{id}', [ForDeliveryController::class, 'publicUpdateStatus'])->name('workspaces.csr.rmo-management.updateStatus');
    Route::post('/workspaces/{workspace}/csr/rmo-management/{id}/assign', [ForDeliveryController::class, 'publicAssignUser'])->name('workspaces.csr.rmo-management.assign');
    Route::post('/workspaces/{workspace}/csr/rmo-management/{id}/remove-assignee', [ForDeliveryController::class, 'publicRemoveAssignee'])->name('workspaces.csr.rmo-management.removeAssignee');
    Route::post('/workspaces/{workspace}/csr/rmo-management/{id}/update-phones', [ForDeliveryController::class, 'publicUpdatePhones'])->name('workspaces.csr.rmo-management.updatePhones');
    Route::get('/workspaces/{workspace}/csr/rmo-management/call-logs', [ForDeliveryController::class, 'callLogs'])->name('workspaces.csr.rmo-management.callLogs');
    Route::get('/workspaces/{workspace}/csr/rmo-management/call-logs/export', [ForDeliveryController::class, 'publicExportCallLogs'])->name('workspaces.csr.rmo-management.callLogs.export');
    Route::get('/workspaces/{workspace}/csr/rmo-management/call-logs/breakdown', [ForDeliveryController::class, 'callLogsBreakdown'])->name('workspaces.csr.rmo-management.callLogs.breakdown');

    // Checklist routes
    Route::get('/workspaces/{workspace}/checklist', [ChecklistController::class, 'index'])->name('workspaces.checklist.index');
    Route::post('/workspaces/{workspace}/checklist', [ChecklistController::class, 'store'])->name('workspaces.checklist.store');
    Route::put('/workspaces/{workspace}/checklist/{checklist}', [ChecklistController::class, 'update'])->name('workspaces.checklist.update');
    Route::delete('/workspaces/{workspace}/checklist/{checklist}', [ChecklistController::class, 'destroy'])->name('workspaces.checklist.destroy');
    Route::get('/workspaces/{workspace}/checklist/progress/{target}/{targetId}', [ChecklistProgressController::class, 'index'])->name('workspaces.checklist.progress.index');
    Route::post('/workspaces/{workspace}/checklist/progress/{target}/{targetId}', [ChecklistProgressController::class, 'store'])->name('workspaces.checklist.progress.store');
    Route::delete('/workspaces/{workspace}/checklist/progress/{target}/{targetId}', [ChecklistProgressController::class, 'destroy'])->name('workspaces.checklist.progress.destroy');

    // Team routes
    Route::get('/workspaces/{workspace}/teams', [TeamController::class, 'index'])->name('workspaces.teams.index');
    Route::post('/workspaces/{workspace}/teams', [TeamController::class, 'store'])->name('workspaces.teams.store');
    Route::put('/workspaces/{workspace}/teams/{team}', [TeamController::class, 'update'])->name('workspaces.teams.update');
    Route::delete('/workspaces/{workspace}/teams/{team}', [TeamController::class, 'destroy'])->name('workspaces.teams.destroy');
    Route::get('/workspaces/{workspace}/teams/{team}/schedule', [TeamScheduleController::class, 'index'])->name('workspaces.teams.schedule');
    Route::put('/workspaces/{workspace}/teams/{team}/schedule', [TeamScheduleController::class, 'update'])->name('workspaces.teams.schedule.update');

    // Team ad-spend goal routes (daily target per team)
    // Moved to the S&M dashboard's "Ad Spend Goals" tab — keep the old list URL
    // working by redirecting to the new tab route.
    Route::get('/workspaces/{workspace}/ad-spend-goals', fn (Workspace $workspace) => redirect()->route('workspaces.sales-marketing.dashboard.ad-spend-goals', $workspace))->name('workspaces.ad-spend-goals.index');
    Route::get('/workspaces/{workspace}/ad-spend-goals/{goal}', [TeamAdSpendGoalController::class, 'show'])->name('workspaces.ad-spend-goals.show');
    Route::post('/workspaces/{workspace}/ad-spend-goals', [TeamAdSpendGoalController::class, 'store'])->name('workspaces.ad-spend-goals.store');
    Route::put('/workspaces/{workspace}/ad-spend-goals/{goal}', [TeamAdSpendGoalController::class, 'update'])->name('workspaces.ad-spend-goals.update');
    Route::delete('/workspaces/{workspace}/ad-spend-goals/{goal}', [TeamAdSpendGoalController::class, 'destroy'])->name('workspaces.ad-spend-goals.destroy');

    // Department routes
    Route::get('/workspaces/{workspace}/departments', [DepartmentController::class, 'index'])->name('workspaces.departments.index');
    Route::post('/workspaces/{workspace}/departments', [DepartmentController::class, 'store'])->name('workspaces.departments.store');
    Route::put('/workspaces/{workspace}/departments/{department}', [DepartmentController::class, 'update'])->name('workspaces.departments.update');
    Route::delete('/workspaces/{workspace}/departments/{department}', [DepartmentController::class, 'destroy'])->name('workspaces.departments.destroy');

    // Team data-access assignment (team-level visibility)
    Route::get('/workspaces/{workspace}/teams/{team}/shops', [TeamShopController::class, 'index'])->name('workspaces.teams.shops.index');
    Route::put('/workspaces/{workspace}/teams/{team}/shops', [TeamShopController::class, 'update'])->name('workspaces.teams.shops.update');
    Route::get('/workspaces/{workspace}/teams/{team}/ad-accounts', [TeamAdAccountController::class, 'index'])->name('workspaces.teams.ad-accounts.index');
    Route::put('/workspaces/{workspace}/teams/{team}/ad-accounts', [TeamAdAccountController::class, 'update'])->name('workspaces.teams.ad-accounts.update');

    Route::get('/workspaces/{workspace}/botcake', function (Workspace $workspace) {
        return redirect()->route('workspaces.botcake.sequences.index', $workspace);
    })->name('workspaces.botcake');
    Route::get('/workspaces/{workspace}/botcake/flows', [FlowController::class, 'index'])->name('workspaces.botcake.flows.index');
    Route::get('/workspaces/{workspace}/botcake/sequences', [SequenceController::class, 'index'])->name('workspaces.botcake.sequences.index');
    Route::get('/workspaces/{workspace}/botcake/sequence-messages', [SequenceMessageController::class, 'index'])->name('workspaces.botcake.sequence-messages.index');

    Route::get('/workspaces/{workspace}/inventory/dashboard', [InventoryDashboardController::class, 'index'])
        ->name('workspaces.inventory.dashboard');

    Route::prefix('/workspaces/{workspace}/inventory/items')->name('workspaces.inventory.item.')->group(function () {
        Route::get('/', [InventoryItemController::class, 'index'])->name('index');
        Route::get('/export', [InventoryItemController::class, 'export'])->name('export');
        Route::post('/', [InventoryItemController::class, 'store'])->name('store');
        Route::post('/sync-erp', [InventoryItemController::class, 'syncFromErp'])->name('sync-erp');
        Route::post('/bulk-status', [InventoryItemController::class, 'bulkUpdateStatus'])->name('bulk-status');
        Route::post('/bulk-product', [InventoryItemController::class, 'bulkUpdateProduct'])->name('bulk-product');
        Route::post('/bulk-group', [InventoryItemController::class, 'bulkGroup'])->name('bulk-group');
        Route::get('/{item}/stock-as-of', [InventoryItemController::class, 'stockAsOf'])->name('stock-as-of');
        Route::get('/{item}/pending-purchase-orders', [InventoryItemController::class, 'pendingPurchaseOrders'])->name('pending-purchase-orders');
        Route::post('/{item}/discrepancies', [InventoryItemController::class, 'adjustCount'])->name('discrepancies.store');
        Route::patch('/{item}/lead-time', [InventoryItemController::class, 'updateLeadTime'])->name('lead-time.update');
        Route::put('/{item}', [InventoryItemController::class, 'update'])->name('update');
        Route::delete('/{item}', [InventoryItemController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('/workspaces/{workspace}/pancake/courier-shipments')->name('workspaces.pancake.courier-shipments.')->group(function () {
        Route::get('/', [CourierShipmentController::class, 'index'])->name('index');
        Route::post('/import', [CourierShipmentController::class, 'import'])->name('import');
    });

    Route::get('/workspaces/{workspace}/pancake/orders', [OrderController::class, 'index'])
        ->name('workspaces.pancake.orders.index');

    Route::prefix('/workspaces/{workspace}/inventory/purchased-orders')->name('workspaces.inventory.purchased-orders.')->group(function () {
        Route::get('/', [PurchasedOrderController::class, 'index'])->name('index');
        Route::get('/export', [PurchasedOrderController::class, 'export'])->name('export');
        Route::get('/create', [PurchasedOrderController::class, 'create'])->name('create');
        Route::post('/', [PurchasedOrderController::class, 'store'])->name('store');
        Route::get('/{purchasedOrder}/edit', [PurchasedOrderController::class, 'edit'])->name('edit');
        Route::put('/{purchasedOrder}', [PurchasedOrderController::class, 'update'])->name('update');
        Route::delete('/{purchasedOrder}', [PurchasedOrderController::class, 'destroy'])->name('destroy');
    });

    // PO monitoring is merged into the purchased-orders page (no standalone page);
    // these remain as the mutation endpoints the expanded PO rows call into.
    // Delivery timeliness is derived/read-only, so it has no update route.
    Route::prefix('/workspaces/{workspace}/inventory/po-monitoring')->name('workspaces.inventory.po-monitoring.')->group(function () {
        Route::post('/items/{purchasedOrderItem}/deliveries', [PurchaseOrderMonitoringController::class, 'storeDelivery'])->name('deliveries.store');
        Route::put('/deliveries/{delivery}', [PurchaseOrderMonitoringController::class, 'updateDelivery'])->name('deliveries.update');
        Route::delete('/deliveries/{delivery}', [PurchaseOrderMonitoringController::class, 'destroyDelivery'])->name('deliveries.destroy');
        Route::put('/orders/{purchasedOrder}/expected-delivery', [PurchaseOrderMonitoringController::class, 'updateExpectedDelivery'])->name('orders.expected-delivery');
        Route::put('/orders/{purchasedOrder}/status', [PurchaseOrderMonitoringController::class, 'updateStatus'])->name('orders.status');
        Route::put('/items/{purchasedOrderItem}/remarks', [PurchaseOrderMonitoringController::class, 'updateRemarks'])->name('items.remarks');
    });

    Route::prefix('/workspaces/{workspace}/gencys')->name('workspaces.gencys.')->group(function () {
        Route::get('/daily-sales-tracker', [GencysDailySalesTrackerController::class, 'index'])->name('daily-sales-tracker.index');

        // Hand-made test orders. TESTING ONLY — not registered in production, so
        // the endpoints don't exist there at all (the controller also aborts).
        if (! app()->isProduction()) {
            Route::post('/daily-sales-tracker', [GencysDailySalesTrackerController::class, 'store'])->name('daily-sales-tracker.store');
            Route::delete('/daily-sales-tracker/{order}', [GencysDailySalesTrackerController::class, 'destroy'])->name('daily-sales-tracker.destroy');
        }

        // ERP syncing lives together under Gencys: the runs for this workspace
        // and the batch queue that sends them, both gated on "View Gencys Sync".
        // Per-run actions offered beside a batch's runs. No listing route: runs
        // are read on the batch pages, these are just the things you can do to one.
        Route::prefix('/sync-runs')->name('sync-runs.')->middleware('can:View Gencys Sync,workspace')->group(function () {
            // Asked from the page per row, so JSON rather than an Inertia reload.
            Route::get('/{run}/execution', [GencysSyncRunController::class, 'execution'])->name('execution');
            Route::post('/{run}/retry', [GencysSyncRunController::class, 'retry'])->name('retry');
        });

        Route::prefix('/sync-batches')->name('sync-batches.')->middleware('can:View Gencys Sync,workspace')->group(function () {
            Route::get('/', [GencysSyncBatchController::class, 'index'])->name('index');
            Route::get('/{batch}', [GencysSyncBatchController::class, 'show'])->name('show');
            Route::post('/', [GencysSyncBatchController::class, 'store'])->name('store');
            Route::post('/{batch}/cancel', [GencysSyncBatchController::class, 'cancel'])->name('cancel');
        });

        Route::get('/interns', [GencysInternController::class, 'index'])->name('interns.index');
        Route::post('/interns/sync', [GencysInternController::class, 'sync'])->name('interns.sync');
        Route::patch('/interns/{intern}/toggle-active', [GencysInternController::class, 'toggleActive'])->name('interns.toggle-active');
        Route::patch('/interns/{intern}/assign-user', [GencysInternController::class, 'assignUser'])->name('interns.assign-user');
        Route::patch('/interns/{intern}/other-names', [GencysInternController::class, 'updateOtherNames'])->name('interns.other-names');
        Route::get('/pages', [GencysPageController::class, 'index'])->name('pages.index');
        Route::post('/pages/sync', [GencysPageController::class, 'sync'])->name('pages.sync');
        Route::get('/unit-codes', [UnitCodeController::class, 'index'])->name('unit-codes.index');
        Route::post('/unit-codes/sync', [UnitCodeController::class, 'sync'])->name('unit-codes.sync');
        Route::post('/unit-codes/bulk-product', [UnitCodeController::class, 'bulkUpdateProduct'])->name('unit-codes.bulk-product');
        Route::post('/unit-codes', [UnitCodeController::class, 'store'])->name('unit-codes.store');
        Route::patch('/unit-codes/{unitCode}/product', [UnitCodeController::class, 'updateProduct'])->name('unit-codes.update-product');
        Route::put('/unit-codes/{unitCode}', [UnitCodeController::class, 'update'])->name('unit-codes.update');
        Route::delete('/unit-codes/{unitCode}', [UnitCodeController::class, 'destroy'])->name('unit-codes.destroy');
    });

    Route::prefix('/workspaces/{workspace}/inventory/items')->name('workspaces.inventory.item.')->group(function () {
        Route::get('/', [InventoryItemController::class, 'index'])->name('index');
        Route::post('/', [InventoryItemController::class, 'store'])->name('store');
        Route::put('/{item}', [InventoryItemController::class, 'update'])->name('update');
        Route::delete('/{item}', [InventoryItemController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('/workspaces/{workspace:slug}/finance')->name('workspaces.finance.')->group(function () {
        Route::get('/dashboard', FinanceDashboardController::class)->name('dashboard');
        Route::get('/expenses', FinanceExpensesController::class)->name('expenses');

        Route::get('/accounts', [FinanceAccountController::class, 'index'])->name('accounts.index');
        Route::post('/accounts', [FinanceAccountController::class, 'store'])->name('accounts.store');
        Route::get('/accounts/{account}', [FinanceAccountController::class, 'show'])->name('accounts.show');
        Route::put('/accounts/{account}', [FinanceAccountController::class, 'update'])->name('accounts.update');
        Route::delete('/accounts/{account}', [FinanceAccountController::class, 'destroy'])->name('accounts.destroy');

        Route::get('/transactions', [FinanceTransactionController::class, 'index'])->name('transactions.index');
        Route::get('/transactions/create', [FinanceTransactionController::class, 'create'])->name('transactions.create');
        Route::get('/transactions/export', [FinanceTransactionController::class, 'export'])->name('transactions.export');
        Route::get('/transactions/{transaction}/edit', [FinanceTransactionController::class, 'edit'])->name('transactions.edit');
        Route::post('/transactions', [FinanceTransactionController::class, 'store'])->name('transactions.store');
        Route::post('/transactions/import', [FinanceTransactionController::class, 'import'])->name('transactions.import');
        Route::put('/transactions/bulk-update-type', [FinanceTransactionController::class, 'bulkUpdateType'])->name('transactions.bulk-update-type');
        Route::put('/transactions/bulk-update-sub-category', [FinanceTransactionController::class, 'bulkUpdateSubCategory'])->name('transactions.bulk-update-sub-category');
        Route::put('/transactions/{transaction}', [FinanceTransactionController::class, 'update'])->name('transactions.update');
        Route::delete('/transactions/{transaction}', [FinanceTransactionController::class, 'destroy'])->name('transactions.destroy');

        Route::get('/income-statements', [FinanceIncomeStatementController::class, 'index'])->name('income-statements.index');
        Route::get('/income-statements/preview', [FinanceIncomeStatementController::class, 'preview'])->name('income-statements.preview');
        Route::post('/income-statements', [FinanceIncomeStatementController::class, 'store'])->name('income-statements.store');
        Route::get('/income-statements/{incomeStatement}', [FinanceIncomeStatementController::class, 'show'])->name('income-statements.show');
        Route::get('/income-statements/{incomeStatement}/export', [FinanceIncomeStatementController::class, 'export'])->name('income-statements.export');
        Route::post('/income-statements/{incomeStatement}/regenerate', [FinanceIncomeStatementController::class, 'regenerate'])->name('income-statements.regenerate');
        Route::delete('/income-statements/{incomeStatement}', [FinanceIncomeStatementController::class, 'destroy'])->name('income-statements.destroy');

        Route::get('/income-statements/{incomeStatement}/products', [FinanceUserIncomeStatementController::class, 'productIndex'])->name('income-statements.products.index');
        Route::get('/income-statements/{incomeStatement}/users', [FinanceUserIncomeStatementController::class, 'index'])->name('income-statements.users.index');

        Route::get('/transaction-types', [FinanceTransactionTypeController::class, 'index'])->name('transaction-types.index');
        Route::post('/transaction-types', [FinanceTransactionTypeController::class, 'store'])->name('transaction-types.store');
        Route::put('/transaction-types/{transactionType}', [FinanceTransactionTypeController::class, 'update'])->name('transaction-types.update');
        Route::delete('/transaction-types/{transactionType}', [FinanceTransactionTypeController::class, 'destroy'])->name('transaction-types.destroy');

        Route::get('/remittances', [FinanceRemittanceController::class, 'index'])->name('remittances.index');
        Route::post('/remittances', [FinanceRemittanceController::class, 'store'])->name('remittances.store');
        Route::post('/remittances/import', [FinanceRemittanceController::class, 'import'])->name('remittances.import');
        Route::get('/remittances/{remittance}', [FinanceRemittanceController::class, 'show'])->name('remittances.show');
        Route::post('/remittances/{remittance}/import-items', [FinanceRemittanceController::class, 'importItems'])->name('remittances.import-items');
        Route::delete('/remittances/{remittance}/items', [FinanceRemittanceController::class, 'clearItems'])->name('remittances.items.clear');
        Route::put('/remittances/{remittance}', [FinanceRemittanceController::class, 'update'])->name('remittances.update');
        Route::delete('/remittances/{remittance}', [FinanceRemittanceController::class, 'destroy'])->name('remittances.destroy');

        Route::get('/request-funds', [FinanceFundRequestController::class, 'index'])->name('request-funds.index');
        Route::post('/request-funds', [FinanceFundRequestController::class, 'store'])->name('request-funds.store');
        Route::put('/request-funds/{requestFund}/status', [FinanceFundRequestController::class, 'updateStatus'])->name('request-funds.status');
        Route::put('/request-funds/{requestFund}', [FinanceFundRequestController::class, 'update'])->name('request-funds.update');
        Route::delete('/request-funds/{requestFund}', [FinanceFundRequestController::class, 'destroy'])->name('request-funds.destroy');
    });

    Route::prefix('/workspaces/{workspace:slug}/creatives')->name('workspaces.creatives.')->group(function () {
        Route::get('/', [CreativesController::class, 'index'])->name('index');
        Route::get('/create', [CreativesController::class, 'create'])->name('create');
        Route::post('/', [CreativesController::class, 'store'])->name('store');
        Route::post('/bulk-reviewers', [CreativesController::class, 'bulkAssignReviewers'])->name('reviewers.bulk');
        Route::get('/{creative}/edit', [CreativesController::class, 'edit'])->name('edit');
        Route::put('/{creative}', [CreativesController::class, 'update'])->name('update');
        Route::delete('/{creative}', [CreativesController::class, 'destroy'])->name('destroy');
        Route::post('/{creative}/reviews', [CreativesController::class, 'addReview'])->name('reviews.store');
        Route::put('/{creative}/reviews/{review}', [CreativesController::class, 'updateReview'])->name('reviews.update');
        Route::put('/{creative}/ads-campaign', [CreativesController::class, 'updateAdsCampaign'])->name('ads-campaign.update');
    });

    Route::prefix('/workspaces/{workspace:slug}/sms')->name('workspaces.sms.')->group(function () {
        Route::get('/send', [SmsController::class, 'create'])->name('send');
        Route::post('/send', [SmsController::class, 'store'])->name('store');
        Route::post('/bulk', [SmsController::class, 'bulkStore'])->name('bulk');
        Route::get('/outbox', [SmsController::class, 'outbox'])->name('outbox');
        Route::get('/sims', [SmsController::class, 'sims'])->name('sims');
    });

    // A workspace's own invoices — the admin list narrowed to one workspace.
    Route::prefix('/workspaces/{workspace:slug}/billing')->name('workspaces.billing.')->group(function () {
        Route::get('/invoices', [BillingInvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}/download', [BillingInvoiceController::class, 'download'])
            ->name('invoices.download');
    });

    Route::prefix('/workspaces/{workspace:slug}/courses')->name('workspaces.courses.')->group(function () {
        Route::get('/', [CoursesController::class, 'index'])->name('index');
        Route::post('/', [CoursesController::class, 'store'])->name('store');
        // Literal path, registered before /{course} so it isn't swallowed.
        Route::post('/cover/presign', [CoursesController::class, 'presignCover'])->name('cover.presign');
        // Literal segment before the parameterised one, or /preview would be
        // swallowed by {course}.
        Route::get('/{course}/preview', [CoursesController::class, 'preview'])->name('preview');
        Route::get('/{course}', [CoursesController::class, 'show'])->name('show');
        Route::put('/{course}', [CoursesController::class, 'update'])->name('update');
        Route::delete('/{course}', [CoursesController::class, 'destroy'])->name('destroy');
        // The bucket is private, so course media is reached through the app.
        Route::get('/{course}/media/{media}', [CoursesController::class, 'showMedia'])->name('media.show');

        // A course's structure: modules, and the lessons inside them. Both are
        // edited from the course detail page and gated by "Edit Courses".
        Route::post('/{course}/modules', [CourseModuleController::class, 'store'])->name('modules.store');
        Route::put('/{course}/modules/{module}', [CourseModuleController::class, 'update'])->name('modules.update');
        Route::delete('/{course}/modules/{module}', [CourseModuleController::class, 'destroy'])->name('modules.destroy');

        Route::post('/{course}/modules/{module}/lessons', [CourseLessonController::class, 'store'])->name('lessons.store');
        Route::put('/{course}/modules/{module}/lessons/{lesson}', [CourseLessonController::class, 'update'])->name('lessons.update');
        Route::delete('/{course}/modules/{module}/lessons/{lesson}', [CourseLessonController::class, 'destroy'])->name('lessons.destroy');

        // A learner's own run through the course.
        Route::post('/{course}/start', [CourseProgressController::class, 'start'])->name('start');
        Route::post('/{course}/modules/{module}/lessons/{lesson}/complete', [CourseProgressController::class, 'complete'])->name('lessons.complete');
        Route::delete('/{course}/modules/{module}/lessons/{lesson}/complete', [CourseProgressController::class, 'uncomplete'])->name('lessons.uncomplete');

        // A lesson's video. Playback redirects to a signed URL so the browser
        // streams from S3 rather than through PHP.
        // Presigned direct-to-S3: the browser PUTs the file to the bucket and
        // only tells us the key, so nginx and PHP never see the bytes.
        Route::post('/{course}/modules/{module}/lessons/{lesson}/video/presign', [CourseLessonController::class, 'presignVideo'])->name('lessons.video.presign');
        Route::post('/{course}/modules/{module}/lessons/{lesson}/video/attach', [CourseLessonController::class, 'attachVideo'])->name('lessons.video.attach');
        // Fallback for disks that cannot sign an upload.
        Route::post('/{course}/modules/{module}/lessons/{lesson}/video', [CourseLessonController::class, 'storeVideo'])->name('lessons.video.store');
        Route::get('/{course}/modules/{module}/lessons/{lesson}/video', [CourseLessonController::class, 'showVideo'])->name('lessons.video.show');
        Route::delete('/{course}/modules/{module}/lessons/{lesson}/video', [CourseLessonController::class, 'destroyVideo'])->name('lessons.video.destroy');
    });

    Route::get('/workspaces/{workspace:slug}/support', [SupportTicketController::class, 'index'])->name('support.index');
    Route::post('/workspaces/{workspace:slug}/support', [SupportTicketController::class, 'store'])->name('support.store');
    Route::patch('/workspaces/{workspace:slug}/support/{ticket}', [SupportTicketController::class, 'update'])->name('support.update');
    Route::delete('/workspaces/{workspace:slug}/support/{ticket}', [SupportTicketController::class, 'destroy'])->name('support.destroy');

});

// Public invitation routes (guest or authenticated)
Route::get('/workspaces/invitations/{token}', [WorkspaceInvitationController::class, 'show'])->name('workspaces.invitations.show');
Route::get('/workspaces/invitations/{token}/accept', [WorkspaceInvitationController::class, 'accept'])->name('workspaces.invitations.accept');

Route::prefix('/workspaces/{workspace:slug}')->group(function () {
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    Route::post('/roles/{role}/restore', [RoleController::class, 'restore'])
        ->withTrashed()
        ->name('roles.restore');

    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/archived', [RoleController::class, 'archived'])->name('roles.archived');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::patch('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');

    Route::get('/roles/{role}/permissions', [RolePermissionController::class, 'edit'])->name('roles.permissions.edit');
    Route::put('/roles/{role}/permissions', [RolePermissionController::class, 'update'])->name('roles.permissions.update');

});

// Admin Routes //
Route::middleware(['auth', 'verified', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Workspace Management
        Route::get('/workspaces', [AdminWorkspaceController::class, 'index'])
            ->name('workspaces.index');

        // Per-client (workspace) report dashboard
        Route::get('/workspaces/{workspace}/report', [AdminClientReportController::class, 'show'])
            ->name('workspaces.report');

        Route::put('/workspaces/{workspace}/subscription', [AdminWorkspaceController::class, 'updateSubscription'])
            ->name('workspaces.update-subscription');
        Route::put('/workspaces/{workspace}/modules', [AdminWorkspaceController::class, 'updateModules'])
            ->name('workspaces.update-modules');
        Route::put('/workspaces/{workspace}/max-shops', [AdminWorkspaceController::class, 'updateMaxShops'])
            ->name('workspaces.update-max-shops');

        // User Management
        Route::get('/users', [AdminUserController::class, 'index'])
            ->name('users.index');
        Route::post('/users/{user}/reset-password', [AdminUserController::class, 'generatePasswordReset'])
            ->name('users.reset-password');
        Route::patch('/users/{user}/super-admin', [AdminUserController::class, 'updateSuperAdmin'])
            ->name('users.update-super-admin');

        Route::get('/support-tickets', [AdminSupportTicketController::class, 'index'])
            ->name('support-tickets.index');

        // Global, cross-workspace activity log.
        Route::get('/activity-logs', [AdminActivityLogController::class, 'index'])
            ->name('activity-logs.index');
        Route::patch('/support-tickets/{ticket}', [AdminSupportTicketController::class, 'update'])
            ->name('support-tickets.update');

        // Metric Setting Controller
        Route::get('workspaces/{workspace}/metrics/edit', [MetricSettingController::class, 'edit'])
            ->name('workspaces.metric-settings.edit');

        Route::put('/workspaces/{workspace}/metrics', [MetricSettingController::class, 'update'])
            ->name('workspaces.metric-settings.update');

        // Subscription Plans Management
        Route::get('/subscription-plans', [AdminSubscriptionPlanController::class, 'index'])
            ->name('subscription-plans.index');
        Route::get('/subscription-plans/create', [AdminSubscriptionPlanController::class, 'create'])
            ->name('subscription-plans.create');
        Route::post('/subscription-plans', [AdminSubscriptionPlanController::class, 'store'])
            ->name('subscription-plans.store');
        Route::get('/subscription-plans/{subscriptionPlan}/edit', [AdminSubscriptionPlanController::class, 'edit'])
            ->name('subscription-plans.edit');
        Route::put('/subscription-plans/{subscriptionPlan}', [AdminSubscriptionPlanController::class, 'update'])
            ->name('subscription-plans.update');
        Route::delete('/subscription-plans/{subscriptionPlan}', [AdminSubscriptionPlanController::class, 'destroy'])
            ->name('subscription-plans.destroy');

        // Invoices
        Route::get('/invoices', [AdminInvoiceController::class, 'index'])
            ->name('invoices.index');
        Route::get('/invoices/create', [AdminInvoiceController::class, 'create'])
            ->name('invoices.create');
        Route::post('/invoices', [AdminInvoiceController::class, 'store'])
            ->name('invoices.store');
        Route::get('/invoices/{invoice}/download', [AdminInvoiceController::class, 'download'])
            ->name('invoices.download');
        Route::patch('/invoices/{invoice}/status', [AdminInvoiceController::class, 'updateStatus'])
            ->name('invoices.update-status');

        // Proof of payment — the receipt evidencing an invoice was settled.
        Route::get('/invoices/{invoice}/proof', [AdminInvoiceController::class, 'showProof'])
            ->name('invoices.proof.show');
        Route::post('/invoices/{invoice}/proof', [AdminInvoiceController::class, 'storeProof'])
            ->name('invoices.proof.store');
        Route::delete('/invoices/{invoice}/proof', [AdminInvoiceController::class, 'destroyProof'])
            ->name('invoices.proof.destroy');
        Route::delete('/invoices/{invoice}', [AdminInvoiceController::class, 'destroy'])
            ->name('invoices.destroy');

        // Workspace SIM management (SimGateway module)
        Route::get('/sims', [AdminSimController::class, 'index'])
            ->name('sims.index');
        Route::get('/sims/create', [AdminSimController::class, 'create'])
            ->name('sims.create');
        Route::post('/sims', [AdminSimController::class, 'store'])
            ->name('sims.store');
        Route::get('/sims/{sim}/edit', [AdminSimController::class, 'edit'])
            ->name('sims.edit');
        Route::put('/sims/{sim}', [AdminSimController::class, 'update'])
            ->name('sims.update');
        Route::delete('/sims/{sim}', [AdminSimController::class, 'destroy'])
            ->name('sims.destroy');
    });
