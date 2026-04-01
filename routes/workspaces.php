<?php

use App\Http\Controllers\Workspaces\AdAccountController;
use App\Http\Controllers\Workspaces\AdsManager\AdController;
use App\Http\Controllers\Workspaces\AdsManager\AdSetController;
use App\Http\Controllers\Workspaces\AdsManager\CampaignController;
use App\Http\Controllers\Workspaces\AdsManager\OptimizationRuleController;
use App\Http\Controllers\Workspaces\Botcake\FlowController;
use App\Http\Controllers\Workspaces\Botcake\SequenceController;
use App\Http\Controllers\Workspaces\EmployeeController;
use App\Http\Controllers\Workspaces\FacebookAccountController;
use App\Http\Controllers\Workspaces\PageController;
use App\Http\Controllers\Workspaces\ProductController;
use App\Http\Controllers\Workspaces\Record\RTSController;
use App\Http\Controllers\Workspaces\Record\SalesController;
use App\Http\Controllers\Workspaces\RoleController;
use App\Http\Controllers\Workspaces\InventoryTransactionController;
use App\Http\Controllers\Workspaces\RTS\AnalyticController;
use App\Http\Controllers\Workspaces\RTS\ForDeliveryController;
use App\Http\Controllers\Workspaces\RTS\ParcelUpdateNotificationController;
use App\Http\Controllers\Workspaces\RTS\ParcelUpdateNotificationTemplateController;
use App\Http\Controllers\Workspaces\TeamController;
use App\Http\Controllers\Workspaces\WorkspaceController;
use App\Http\Controllers\Workspaces\WorkspaceInvitationController;
use App\Http\Controllers\Workspaces\WorkspaceMemberController;
use App\Http\Controllers\Workspaces\WorkspaceSetupController;
use App\Models\Workspace;
use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\PpwController;
use Modules\Inventory\Http\Controllers\PurchaseOrderController;

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
Route::post('/public/workspaces/{workspace}/rts/rmo-management/{id}', [ForDeliveryController::class, 'publicUpdateStatus'])->name('public-page.rmo-management.updateStatus');

Route::middleware(['auth'])->group(function () {
    // Workspace setup (first-time after registration)
    Route::get('/workspaces/setup', [WorkspaceSetupController::class, 'create'])->name('workspaces.setup');
    Route::post('/workspaces/setup', [WorkspaceSetupController::class, 'store'])->name('workspaces.setup.store');


    Route::prefix('workspaces/{workspace:slug}')->group(function () {
        Route::get('/inventory_transaction', [InventoryTransactionController::class, 'index'])->name('inventory.transactions.index');
        Route::post('/inventory_transaction', [InventoryTransactionController::class, 'store'])->name('inventory.transactions.store');
        Route::patch('/inventory_transaction/{inventory}', [InventoryTransactionController::class, 'update'])->name('inventory.transactions.update');
        Route::delete('/inventory_transaction/{inventory}', [InventoryTransactionController::class, 'destroy'])->name('inventory.transactions.destroy');
    });



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
    //    Route::get('/workspaces/{workspace}/rts/for-delivery-today', [ForDeliveryController::class, 'index'])->name('workspaces.rts.for-delivery-today');
    Route::get('/workspaces/{workspace}/rts/parcel-journeys', [ParcelUpdateNotificationTemplateController::class, 'index'])->name('workspaces.rts.parcel-journeys');
    Route::get('/workspaces/{workspace}/rts/parcel-update-notification', [ParcelUpdateNotificationController::class, 'index'])->name('workspaces.rts.parcel-update-notification');
    Route::get('/workspaces/{workspace}/rts/parcel-journey-notification-templates', [ParcelUpdateNotificationTemplateController::class, 'index'])->name('workspaces.rts.parcel-journey-notification-templates.index');
    Route::put('/workspaces/{workspace}/rts/parcel-journey-notification-templates/{template}', [ParcelUpdateNotificationTemplateController::class, 'update'])->name('workspaces.rts.parcel-journey-notification-templates.update');
    Route::get('/workspaces/{workspace}/records/sales', [SalesController::class, 'index'])->name('workspaces.records.sales');
    Route::get('/workspaces/{workspace}/records/rts', [RTSController::class, 'index'])->name('workspaces.records.rts');

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

    // Employee routes
    Route::get('/workspaces/{workspace}/employees', [EmployeeController::class, 'index'])->name('workspaces.employees.index');

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
    Route::get('/workspaces/{workspace}/inventory/purchased-orders', [PurchaseOrderController::class, 'index'])->name('workspaces.inventory.purchased-orders.index');
    Route::get('/workspaces/{workspace}/inventory/purchased-orders/create', [PurchaseOrderController::class, 'create'])->name('workspaces.inventory.purchased-orders.create');

    // PPW ROUTES
    Route::prefix('/workspaces/{workspace}/inventory/ppw')->name('workspaces.inventory.ppw.')->group(function () {
    Route::get('/', [PpwController::class, 'index'])->name('index');
    Route::post('/', [PpwController::class, 'store'])->name('store');
    Route::put('/{ppw}', [PpwController::class, 'update'])->name('update');
    Route::delete('/{ppw}', [PpwController::class, 'destroy'])->name('destroy');
    });

});


// Public invitation routes (guest or authenticated)
Route::get('/workspaces/invitations/{token}', [WorkspaceInvitationController::class, 'show'])->name('workspaces.invitations.show');
Route::get('/workspaces/invitations/{token}/accept', [WorkspaceInvitationController::class, 'accept'])->name('workspaces.invitations.accept');

Route::prefix('/workspaces/{workspace:slug}')->group(function () {

    Route::patch('/roles/{role}/archive', [RoleController::class, 'archive'])->name('roles.archive');
    Route::post('/roles/{role}/restore', [RoleController::class, 'restore'])
        ->withTrashed()
        ->name('roles.restore');

    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::patch('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');



});

