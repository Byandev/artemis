<?php

use App\Http\Controllers\Workspaces\AdAccountController;
use App\Http\Controllers\Workspaces\AdsManager\AdController;
use App\Http\Controllers\Workspaces\AdsManager\AdSetController;
use App\Http\Controllers\Workspaces\AdsManager\CampaignController;
use App\Http\Controllers\Workspaces\AdsManager\OptimizationRuleController;
use App\Http\Controllers\Workspaces\AskDataController;
use App\Http\Controllers\Workspaces\Botcake\FlowController;
use App\Http\Controllers\Workspaces\Botcake\SequenceController;
use App\Http\Controllers\Workspaces\ChecklistController;
use App\Http\Controllers\Workspaces\ChecklistProgressController;
use App\Http\Controllers\Workspaces\CSRController;
use App\Http\Controllers\Workspaces\FacebookAccountController;
use App\Http\Controllers\Workspaces\PageController;
use App\Http\Controllers\Workspaces\ProductController;
use App\Http\Controllers\Workspaces\RoleController;
use App\Http\Controllers\Workspaces\RTS\AnalyticController;
use App\Http\Controllers\Workspaces\RTS\ForDeliveryController;
use App\Http\Controllers\Workspaces\RTS\ParcelUpdateNotificationController;
use App\Http\Controllers\Workspaces\RTS\ParcelUpdateNotificationTemplateController;
use App\Http\Controllers\Workspaces\TeamController;
use App\Http\Controllers\Workspaces\WorkspaceApiKeyController;
use App\Http\Controllers\Workspaces\WorkspaceController;
use App\Http\Controllers\Workspaces\WorkspaceInvitationController;
use App\Http\Controllers\Workspaces\WorkspaceMemberController;
use App\Http\Controllers\Workspaces\WorkspaceSetupController;
use App\Models\Workspace;
use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\AccountController as FinanceAccountController;
use Modules\Finance\Http\Controllers\DashboardController as FinanceDashboardController;
use Modules\Finance\Http\Controllers\RemittanceController as FinanceRemittanceController;
use Modules\Finance\Http\Controllers\TransactionController as FinanceTransactionController;
use Modules\Inventory\Http\Controllers\InventoryItemController;
use Modules\Inventory\Http\Controllers\InventoryTransactionController;
use Modules\Inventory\Http\Controllers\PurchasedOrderController;

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
Route::post('/public/workspaces/{workspace}/rts/rmo-management/{id}', [ForDeliveryController::class, 'publicUpdateStatus'])->name('public-page.rmo-management.updateStatus');
Route::post('/public/workspaces/{workspace}/rts/rmo-management/{id}/assign', [ForDeliveryController::class, 'publicAssignUser'])->name('public-page.rmo-management.assign');
Route::post('/public/workspaces/{workspace}/rts/rmo-management/{id}/remove-assignee', [ForDeliveryController::class, 'publicRemoveAssignee'])->name('public-page.rmo-management.removeAssignee');

Route::middleware(['auth'])->group(function () {
    // Workspace setup (first-time after registration)
    Route::get('/workspaces/setup', [WorkspaceSetupController::class, 'create'])->name('workspaces.setup');
    Route::post('/workspaces/setup', [WorkspaceSetupController::class, 'store'])->name('workspaces.setup.store');

    Route::prefix('workspaces/{workspace:slug}')->group(function () {
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

    // Member management routes
    Route::get('/workspaces/{workspace}/members', [WorkspaceMemberController::class, 'index'])->name('workspaces.members.index');
    //    Route::put('/workspaces/{workspace}/members/{user}', [WorkspaceMemberController::class, 'update'])->name('workspaces.members.update');
    Route::put('/workspaces/{workspace:slug}/members/{user}', [WorkspaceMemberController::class, 'updateMember'])->name('workspaces.members.update');
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
    Route::get('/workspaces/{workspace}/pages/create', [PageController::class, 'create'])->name('workspaces.pages.create');
    Route::post('/workspaces/{workspace}/pages', [PageController::class, 'store'])->name('workspaces.pages.store');
    Route::get('/workspaces/{workspace}/pages/{page}/edit', [PageController::class, 'edit'])->name('workspaces.pages.edit');
    Route::put('/workspaces/{workspace}/pages/{page}', [PageController::class, 'update'])->name('workspaces.pages.update');
    Route::post('/workspaces/{workspace}/pages/{page}/refresh', [PageController::class, 'refresh'])->name('workspaces.pages.refresh');
    Route::post('/workspaces/{workspace}/pages/{page}/archive', [PageController::class, 'archive'])->name('workspaces.pages.archive');
    Route::post('/workspaces/{workspace}/pages/{page}/restore', [PageController::class, 'restore'])->name('workspaces.pages.restore');

    Route::get('/workspaces/{workspace}/shops', [\App\Http\Controllers\Workspaces\ShopController::class, 'index'])->name('workspaces.shops.index');
    Route::post('/workspaces/{workspace}/shops/{shop}/refresh', [\App\Http\Controllers\Workspaces\ShopController::class, 'refresh'])->name('workspaces.shops.refresh');

    // Product routes
    // Redirect to analytics by default for navigation item active state
    Route::get('/workspaces/{workspace}/products', function (Workspace $workspace) {
        return redirect()->route('workspaces.products.analytics', $workspace);
    })->name('workspaces.products');
    Route::get('/workspaces/{workspace}/products/list', [ProductController::class, 'index'])->name('workspaces.products.index');
    Route::get('/workspaces/{workspace}/products/analytics', [\App\Http\Controllers\Workspaces\Product\AnalyticsController::class, 'index'])->name('workspaces.products.analytics');
    Route::get('/workspaces/{workspace}/products/analytics/metrics', [\App\Http\Controllers\Workspaces\Product\AnalyticsController::class, 'metrics'])->name('workspaces-workspace.products.analytics.metrics');
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

    Route::get('/workspaces/{workspace}/facebook-accounts', [FacebookAccountController::class, 'index'])->name('workspaces.facebook-accounts.index');
    Route::get('/workspaces/{workspace}/ad-accounts', [AdAccountController::class, 'index'])->name('workspaces.ad-accounts.index');
    Route::post('/workspaces/{workspace}/ad-accounts/{adAccount}/refresh', [AdAccountController::class, 'refresh'])->name('workspaces.ad-accounts.refresh');

    // Redirect to campaigns by default for navigation item active state
    Route::get('/workspaces/{workspace}/ads-manager', function (Workspace $workspace) {
        return redirect()->route('workspaces.ads-manager.campaigns', $workspace);
    })->name('workspaces.ads-manager');
    Route::get('/workspaces/{workspace}/ads-manager/campaigns', [CampaignController::class, 'index'])->name('workspaces.ads-manager.campaigns');
    Route::get('/workspaces/{workspace}/ads-manager/ad-sets', [AdSetController::class, 'index'])->name('workspaces.ads-manager.ad-sets');
    Route::get('/workspaces/{workspace}/ads-manager/ads', [AdController::class, 'index'])->name('workspaces.ads-manager.ads');
    Route::get('/workspaces/{workspace}/ads-manager/optimization-rules', [OptimizationRuleController::class, 'page'])->name('workspaces.ads-manager.optimization-rules');
    Route::get('/workspaces/{workspace}/ads-manager/optimization-rules/create', [OptimizationRuleController::class, 'create'])->name('workspaces.ads-manager.optimization-rules.create');
    Route::get('/workspaces/{workspace}/ads-manager/optimization-rules/{optimizationRule}/edit', [OptimizationRuleController::class, 'edit'])->name('workspaces.ads-manager.optimization-rules.edit');

    // Optimization Rules API routes
    Route::post('/workspaces/{workspace}/api/optimization-rules', [OptimizationRuleController::class, 'store'])->name('workspaces.api.optimization-rules.store');
    Route::get('/workspaces/{workspace}/api/optimization-rules/{optimizationRule}', [OptimizationRuleController::class, 'show'])->name('workspaces.api.optimization-rules.show');
    Route::put('/workspaces/{workspace}/api/optimization-rules/{optimizationRule}', [OptimizationRuleController::class, 'update'])->name('workspaces.api.optimization-rules.update');
    Route::delete('/workspaces/{workspace}/api/optimization-rules/{optimizationRule}', [OptimizationRuleController::class, 'destroy'])->name('workspaces.api.optimization-rules.destroy');

    Route::put('/workspaces/{workspace:slug}/employees/{employee}', [CSRController::class, 'update'])->name('employees.update');
    Route::get('/workspaces/{workspace}/csr/management', [CSRController::class, 'index'])->name('workspaces.csr.index');
    Route::get('/workspaces/{workspace}/csr/analytics', [CSRController::class, 'analytics'])->name('workspaces.csr.analytics');

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

    Route::get('/workspaces/{workspace}/botcake', function (Workspace $workspace) {
        return redirect()->route('workspaces.botcake.sequences.index', $workspace);
    })->name('workspaces.botcake');
    Route::get('/workspaces/{workspace}/botcake/flows', [FlowController::class, 'index'])->name('workspaces.botcake.flows.index');
    Route::get('/workspaces/{workspace}/botcake/sequences', [SequenceController::class, 'index'])->name('workspaces.botcake.sequences.index');
    Route::prefix('/workspaces/{workspace}/inventory/items')->name('workspaces.inventory.item.')->group(function () {
        Route::get('/', [InventoryItemController::class, 'index'])->name('index');
        Route::post('/', [InventoryItemController::class, 'store'])->name('store');
        Route::put('/{item}', [InventoryItemController::class, 'update'])->name('update');
        Route::delete('/{item}', [InventoryItemController::class, 'destroy'])->name('destroy');
    });
    Route::prefix('/workspaces/{workspace}/inventory/purchased-orders')->name('workspaces.inventory.purchased-orders.')->group(function () {
        Route::get('/', [PurchasedOrderController::class, 'index'])->name('index');
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

        Route::get('/accounts', [FinanceAccountController::class, 'index'])->name('accounts.index');
        Route::post('/accounts', [FinanceAccountController::class, 'store'])->name('accounts.store');
        Route::get('/accounts/{account}', [FinanceAccountController::class, 'show'])->name('accounts.show');
        Route::put('/accounts/{account}', [FinanceAccountController::class, 'update'])->name('accounts.update');
        Route::delete('/accounts/{account}', [FinanceAccountController::class, 'destroy'])->name('accounts.destroy');

        Route::get('/transactions', [FinanceTransactionController::class, 'index'])->name('transactions.index');
        Route::post('/transactions', [FinanceTransactionController::class, 'store'])->name('transactions.store');
        Route::post('/transactions/import', [FinanceTransactionController::class, 'import'])->name('transactions.import');
        Route::put('/transactions/bulk-update-type', [FinanceTransactionController::class, 'bulkUpdateType'])->name('transactions.bulk-update-type');
        Route::put('/transactions/bulk-update-sub-category', [FinanceTransactionController::class, 'bulkUpdateSubCategory'])->name('transactions.bulk-update-sub-category');
        Route::put('/transactions/{transaction}', [FinanceTransactionController::class, 'update'])->name('transactions.update');
        Route::delete('/transactions/{transaction}', [FinanceTransactionController::class, 'destroy'])->name('transactions.destroy');

        Route::get('/remittances', [FinanceRemittanceController::class, 'index'])->name('remittances.index');
        Route::post('/remittances', [FinanceRemittanceController::class, 'store'])->name('remittances.store');
        Route::get('/remittances/{remittance}', [FinanceRemittanceController::class, 'show'])->name('remittances.show');
        Route::put('/remittances/{remittance}', [FinanceRemittanceController::class, 'update'])->name('remittances.update');
        Route::delete('/remittances/{remittance}', [FinanceRemittanceController::class, 'destroy'])->name('remittances.destroy');
    });

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
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::patch('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');

});
