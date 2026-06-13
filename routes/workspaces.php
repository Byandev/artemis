<?php

use App\Http\Controllers\Admin\AdminSubscriptionPlanController;
use App\Http\Controllers\Admin\AdminSupportTicketController;
use App\Http\Controllers\Admin\AdminWorkspaceController;
use App\Http\Controllers\Workspaces\Admin\MetricSettingController;
use App\Http\Controllers\Workspaces\AskDataController;
use App\Http\Controllers\Workspaces\ChecklistController;
use App\Http\Controllers\Workspaces\ChecklistProgressController;
use App\Http\Controllers\Workspaces\CSRController;
use App\Http\Controllers\Workspaces\OnboardingController;
use App\Http\Controllers\Workspaces\PageAccessController;
use App\Http\Controllers\Workspaces\PageController;
use App\Http\Controllers\Workspaces\PageDailyBudgetRecordController;
use App\Http\Controllers\Workspaces\Product\AnalyticsController;
use App\Http\Controllers\Workspaces\ProductController;
use App\Http\Controllers\Workspaces\RoleController;
use App\Http\Controllers\Workspaces\RolePermissionController;
use App\Http\Controllers\Workspaces\RTS\AnalyticController;
use App\Http\Controllers\Workspaces\RTS\ForDeliveryController;
use App\Http\Controllers\Workspaces\RTS\ParcelUpdateNotificationController;
use App\Http\Controllers\Workspaces\RTS\ParcelUpdateNotificationTemplateController;
use App\Http\Controllers\Workspaces\SalesMarketingDashboardController;
use App\Http\Controllers\Workspaces\ShopController;
use App\Http\Controllers\Workspaces\SupportTicketController;
use App\Http\Controllers\Workspaces\TeamController;
use App\Http\Controllers\Workspaces\TeamScheduleController;
use App\Http\Controllers\Workspaces\VideoEditorDashboardController;
use App\Http\Controllers\Workspaces\WorkspaceApiKeyController;
use App\Http\Controllers\Workspaces\WorkspaceController;
use App\Http\Controllers\Workspaces\WorkspaceInvitationController;
use App\Http\Controllers\Workspaces\WorkspaceMemberController;
use App\Http\Controllers\Workspaces\WorkspaceSetupController;
use App\Models\Workspace;
use Illuminate\Support\Facades\Route;
use Modules\Botcake\Http\Controllers\Web\FlowController;
use Modules\Botcake\Http\Controllers\Web\SequenceController;
use Modules\Botcake\Http\Controllers\Web\SequenceMessageController;
use Modules\Creatives\Http\Controllers\CreativesController;
use Modules\Finance\Http\Controllers\AccountController as FinanceAccountController;
use Modules\Finance\Http\Controllers\DashboardController as FinanceDashboardController;
use Modules\Finance\Http\Controllers\ExpensesController as FinanceExpensesController;
use Modules\Finance\Http\Controllers\RemittanceController as FinanceRemittanceController;
use Modules\Finance\Http\Controllers\TransactionController as FinanceTransactionController;
use Modules\Inventory\Http\Controllers\InventoryItemController;
use Modules\Inventory\Http\Controllers\InventoryTransactionController;
use Modules\Inventory\Http\Controllers\PurchasedOrderController;
use Modules\MetaAds\Http\Controllers\AdAccountSyncController;
use Modules\MetaAds\Http\Controllers\AdAccountToggleSyncController;
use Modules\MetaAds\Http\Controllers\AdsManagerController;
use Modules\MetaAds\Http\Controllers\IntegrationsController;
use Modules\MetaAds\Http\Controllers\MetaOAuthController;
use Modules\MetaAds\Http\Controllers\OptimizationRuleController;
use Modules\MetaAds\Http\Controllers\SyncHealthController;
use Modules\Pancake\Http\Controllers\CourierShipmentController;

/*
|--------------------------------------------------------------------------
| Workspace Routes
|--------------------------------------------------------------------------
|
| These routes handle workspace management, including CRUD operations,
| member management, and invitation handling.
|
*/
Route::get('/public/workspaces/{workspace}/rts/rmo-management', [ForDeliveryController::class, 'public'])->name('public-page.rmo-management');
Route::get('/public/workspaces/{workspace}/rts/rmo-management/export', [ForDeliveryController::class, 'publicExport'])->name('public-page.rmo-management.export');
Route::post('/public/workspaces/{workspace}/rts/rmo-management/verify-password', [ForDeliveryController::class, 'verifyPublicPassword'])->name('public-page.rmo-management.verify-password');
Route::post('/public/workspaces/{workspace}/rts/rmo-management/bulk-assign', [ForDeliveryController::class, 'publicBulkAssign'])->name('public-page.rmo-management.bulkAssign');
Route::post('/public/workspaces/{workspace}/rts/rmo-management/{id}', [ForDeliveryController::class, 'publicUpdateStatus'])->name('public-page.rmo-management.updateStatus');
Route::post('/public/workspaces/{workspace}/rts/rmo-management/{id}/assign', [ForDeliveryController::class, 'publicAssignUser'])->name('public-page.rmo-management.assign');
Route::post('/public/workspaces/{workspace}/rts/rmo-management/{id}/remove-assignee', [ForDeliveryController::class, 'publicRemoveAssignee'])->name('public-page.rmo-management.removeAssignee');
Route::post('/public/workspaces/{workspace}/rts/rmo-management/{id}/update-phones', [ForDeliveryController::class, 'publicUpdatePhones'])->name('public-page.rmo-management.updatePhones');
Route::get('/public/workspaces/{workspace}/rts/rmo-management/call-logs', [ForDeliveryController::class, 'callLogs'])->name('public-page.rmo-management.callLogs');

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
        Route::post('/inventory/transactions', [InventoryTransactionController::class, 'store'])->name('inventory.transactions.store');
        Route::patch('/inventory/transactions/{transaction}', [InventoryTransactionController::class, 'update'])->name('inventory.transactions.update');
        Route::delete('/inventory/transactions/{transaction}', [InventoryTransactionController::class, 'destroy'])->name('inventory.transactions.destroy');
    });

    // AI
    Route::post('/workspaces/{workspace}/ask', AskDataController::class)->name('workspace.ask');

    // Workspace dashboard
    Route::get('/workspaces/{workspace}/dashboard', [WorkspaceController::class, 'dashboard'])->name('workspace.dashboard');
    Route::get('/workspaces/{workspace}/chart-data', [WorkspaceController::class, 'getChartData'])->name('workspace.chart-data');

    // Role-specific dashboards (scaffold — gated by granular permissions)
    Route::get('/workspaces/{workspace}/sales-marketing/dashboard', SalesMarketingDashboardController::class)->name('workspaces.sales-marketing.dashboard');
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
    Route::delete('/workspaces/{workspace}/members/{user}', [WorkspaceMemberController::class, 'destroy'])->name('workspaces.members.destroy');
    Route::post('/workspaces/{workspace}/members/{user}/reset-password', [WorkspaceMemberController::class, 'generatePasswordReset'])->name('workspaces.members.reset-password');

    // Page-access control (which pages each member / team may see)
    Route::get('/workspaces/{workspace}/access/users/{user}', [PageAccessController::class, 'editUser'])->name('workspaces.access.users.edit');
    Route::put('/workspaces/{workspace}/access/users/{user}', [PageAccessController::class, 'updateUser'])->name('workspaces.access.users.update');
    Route::get('/workspaces/{workspace}/access/teams/{team}', [PageAccessController::class, 'editTeam'])->name('workspaces.access.teams.edit');
    Route::put('/workspaces/{workspace}/access/teams/{team}', [PageAccessController::class, 'updateTeam'])->name('workspaces.access.teams.update');

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
    Route::get('/workspaces/{workspace}/pages/create', [PageController::class, 'create'])->name('workspaces.pages.create');
    Route::post('/workspaces/{workspace}/pages', [PageController::class, 'store'])->name('workspaces.pages.store');
    Route::post('/workspaces/{workspace}/pages/validate-pos-token', [PageController::class, 'validatePosToken'])->name('workspaces.pages.validate-pos-token');
    Route::post('/workspaces/{workspace}/pages/validate-pancake-token', [PageController::class, 'validatePancakeToken'])->name('workspaces.pages.validate-pancake-token');
    Route::post('/workspaces/{workspace}/pages/validate-botcake-token', [PageController::class, 'validateBotcakeToken'])->name('workspaces.pages.validate-botcake-token');
    Route::get('/workspaces/{workspace}/pages/{page}/edit', [PageController::class, 'edit'])->name('workspaces.pages.edit');
    Route::put('/workspaces/{workspace}/pages/{page}', [PageController::class, 'update'])->name('workspaces.pages.update');
    Route::put('/workspaces/{workspace}/pages/{page}/budget', [PageController::class, 'updateBudget'])->name('workspaces.pages.update-budget');
    Route::post('/workspaces/{workspace}/pages/{page}/refresh', [PageController::class, 'refresh'])->name('workspaces.pages.refresh');
    Route::post('/workspaces/{workspace}/pages/{page}/archive', [PageController::class, 'archive'])->name('workspaces.pages.archive');
    Route::post('/workspaces/{workspace}/pages/{page}/restore', [PageController::class, 'restore'])->name('workspaces.pages.restore');

    // Page Daily Budget Records
    Route::get('/workspaces/{workspace}/page-daily-budget-records', [PageDailyBudgetRecordController::class, 'index'])->name('workspaces.page-daily-budget-records.index');
    Route::post('/workspaces/{workspace}/page-daily-budget-records', [PageDailyBudgetRecordController::class, 'store'])->name('workspaces.page-daily-budget-records.store');
    Route::put('/workspaces/{workspace}/page-daily-budget-records/{pageDailyBudgetRecord}', [PageDailyBudgetRecordController::class, 'update'])->name('workspaces.page-daily-budget-records.update');
    Route::delete('/workspaces/{workspace}/page-daily-budget-records/{pageDailyBudgetRecord}', [PageDailyBudgetRecordController::class, 'destroy'])->name('workspaces.page-daily-budget-records.destroy');

    Route::get('/workspaces/{workspace}/shops', [ShopController::class, 'index'])->name('workspaces.shops.index');
    Route::post('/workspaces/{workspace}/shops/{shop}/refresh-users', [ShopController::class, 'refreshUsers'])->name('workspaces.shops.refresh-users');

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
        ->middleware('can:View Meta Ads,workspace')
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
    Route::get('/workspaces/{workspace}/integrations/meta/health', [SyncHealthController::class, 'index'])
        ->middleware('can:View Meta Ads,workspace')
        ->name('workspaces.metaads.health');

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
        ->middleware('can:View Optimization Rules,workspace')
        ->name('workspaces.metaads.optimization-rules.logs');
    Route::get('/workspaces/{workspace}/integrations/meta/optimization-rules/{optimizationRule}/edit', [OptimizationRuleController::class, 'edit'])
        ->middleware('can:Manage Optimization Rules,workspace')
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
        ->middleware('can:Manage Meta Ads Accounts,workspace')
        ->name('workspaces.metaads.connect');
    Route::post('/workspaces/{workspace}/integrations/meta/users/{metaUser}/sync-ad-accounts', AdAccountSyncController::class)
        ->middleware('can:Manage Meta Ads Accounts,workspace')
        ->name('workspaces.metaads.sync-ad-accounts');
    Route::patch('/workspaces/{workspace}/integrations/meta/ad-accounts/{adAccount}/toggle-sync', AdAccountToggleSyncController::class)
        ->middleware('can:Manage Meta Ads Accounts,workspace')
        ->name('workspaces.metaads.ad-accounts.toggle-sync');

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

    Route::get('/workspaces/{workspace}/botcake', function (Workspace $workspace) {
        return redirect()->route('workspaces.botcake.sequences.index', $workspace);
    })->name('workspaces.botcake');
    Route::get('/workspaces/{workspace}/botcake/flows', [FlowController::class, 'index'])->name('workspaces.botcake.flows.index');
    Route::get('/workspaces/{workspace}/botcake/sequences', [SequenceController::class, 'index'])->name('workspaces.botcake.sequences.index');
    Route::get('/workspaces/{workspace}/botcake/sequence-messages', [SequenceMessageController::class, 'index'])->name('workspaces.botcake.sequence-messages.index');
    Route::prefix('/workspaces/{workspace}/inventory/items')->name('workspaces.inventory.item.')->group(function () {
        Route::get('/', [InventoryItemController::class, 'index'])->name('index');
        Route::post('/', [InventoryItemController::class, 'store'])->name('store');
        Route::put('/{item}', [InventoryItemController::class, 'update'])->name('update');
        Route::delete('/{item}', [InventoryItemController::class, 'destroy'])->name('destroy');
    });
    Route::prefix('/workspaces/{workspace}/pancake/courier-shipments')->name('workspaces.pancake.courier-shipments.')->group(function () {
        Route::get('/', [CourierShipmentController::class, 'index'])->name('index');
        Route::post('/import', [CourierShipmentController::class, 'import'])->name('import');
    });

    Route::prefix('/workspaces/{workspace}/inventory/purchased-orders')->name('workspaces.inventory.purchased-orders.')->group(function () {
        Route::get('/', [PurchasedOrderController::class, 'index'])->name('index');
        Route::get('/export', [PurchasedOrderController::class, 'export'])->name('export');
        Route::get('/create', [PurchasedOrderController::class, 'create'])->name('create');
        Route::post('/', [PurchasedOrderController::class, 'store'])->name('store');
        Route::get('/{purchasedOrder}/edit', [PurchasedOrderController::class, 'edit'])->name('edit');
        Route::put('/{purchasedOrder}', [PurchasedOrderController::class, 'update'])->name('update');
        Route::delete('/{purchasedOrder}', [PurchasedOrderController::class, 'destroy'])->name('destroy');
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
        Route::get('/transactions/export', [FinanceTransactionController::class, 'export'])->name('transactions.export');
        Route::post('/transactions', [FinanceTransactionController::class, 'store'])->name('transactions.store');
        Route::post('/transactions/import', [FinanceTransactionController::class, 'import'])->name('transactions.import');
        Route::put('/transactions/bulk-update-type', [FinanceTransactionController::class, 'bulkUpdateType'])->name('transactions.bulk-update-type');
        Route::put('/transactions/bulk-update-sub-category', [FinanceTransactionController::class, 'bulkUpdateSubCategory'])->name('transactions.bulk-update-sub-category');
        Route::put('/transactions/{transaction}', [FinanceTransactionController::class, 'update'])->name('transactions.update');
        Route::delete('/transactions/{transaction}', [FinanceTransactionController::class, 'destroy'])->name('transactions.destroy');

        Route::get('/remittances', [FinanceRemittanceController::class, 'index'])->name('remittances.index');
        Route::post('/remittances', [FinanceRemittanceController::class, 'store'])->name('remittances.store');
        Route::post('/remittances/import', [FinanceRemittanceController::class, 'import'])->name('remittances.import');
        Route::get('/remittances/{remittance}', [FinanceRemittanceController::class, 'show'])->name('remittances.show');
        Route::post('/remittances/{remittance}/import-items', [FinanceRemittanceController::class, 'importItems'])->name('remittances.import-items');
        Route::delete('/remittances/{remittance}/items', [FinanceRemittanceController::class, 'clearItems'])->name('remittances.items.clear');
        Route::put('/remittances/{remittance}', [FinanceRemittanceController::class, 'update'])->name('remittances.update');
        Route::delete('/remittances/{remittance}', [FinanceRemittanceController::class, 'destroy'])->name('remittances.destroy');
    });

    Route::prefix('/workspaces/{workspace:slug}/creatives')->name('workspaces.creatives.')->group(function () {
        Route::get('/', [CreativesController::class, 'index'])->name('index');
        Route::get('/create', [CreativesController::class, 'create'])->name('create');
        Route::post('/', [CreativesController::class, 'store'])->name('store');
        Route::get('/{creative}/edit', [CreativesController::class, 'edit'])->name('edit');
        Route::put('/{creative}', [CreativesController::class, 'update'])->name('update');
        Route::delete('/{creative}', [CreativesController::class, 'destroy'])->name('destroy');
        Route::post('/{creative}/reviews', [CreativesController::class, 'addReview'])->name('reviews.store');
        Route::put('/{creative}/reviews/{review}', [CreativesController::class, 'updateReview'])->name('reviews.update');
        Route::put('/{creative}/ads-campaign', [CreativesController::class, 'updateAdsCampaign'])->name('ads-campaign.update');
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

        Route::put('/workspaces/{workspace}/subscription', [AdminWorkspaceController::class, 'updateSubscription'])
            ->name('workspaces.update-subscription');
        Route::put('/workspaces/{workspace}/modules', [AdminWorkspaceController::class, 'updateModules'])
            ->name('workspaces.update-modules');
        Route::put('/workspaces/{workspace}/max-pages', [AdminWorkspaceController::class, 'updateMaxPages'])
            ->name('workspaces.update-max-pages');

        Route::get('/support-tickets', [AdminSupportTicketController::class, 'index'])
            ->name('support-tickets.index');
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
    });
