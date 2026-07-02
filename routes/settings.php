<?php

use App\Http\Controllers\Settings\ErpCredentialController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Modules\Inventory\Http\Controllers\Settings\NotificationSettingsController;

Route::middleware('auth')->group(function () {
    Route::redirect('/settings', '/settings/profile');

    Route::get('/settings/profile', [ProfileController::class, 'edit'])->name('account.profile.edit');
    Route::patch('/settings/profile', [ProfileController::class, 'update'])->name('account.profile.update');
    Route::delete('/settings/profile', [ProfileController::class, 'destroy'])->name('account.profile.destroy');

    Route::get('/settings/password', [PasswordController::class, 'edit'])->name('account.password.edit');
    Route::put('/settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('account.password.update');

    Route::redirect('/workspaces/{workspace}/settings', '/workspaces/{workspace}/settings/profile');

    Route::get('/workspaces/{workspace}/settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/workspaces/{workspace}/settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/workspaces/{workspace}/settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/workspaces/{workspace}/settings/password', [PasswordController::class, 'edit'])->name('password.edit');

    Route::put('/workspaces/{workspace}/settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    Route::get('/workspaces/{workspace}/settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance.edit');

    Route::get('/workspaces/{workspace}/settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    // Automation Configuration — ERP credentials consumed by the n8n pipeline.
    Route::get('/workspaces/{workspace}/settings/erp-credentials', [ErpCredentialController::class, 'edit'])
        ->name('erp-credentials.edit');
    Route::put('/workspaces/{workspace}/settings/erp-credentials', [ErpCredentialController::class, 'update'])
        ->name('erp-credentials.update');
    Route::delete('/workspaces/{workspace}/settings/erp-credentials', [ErpCredentialController::class, 'destroy'])
        ->name('erp-credentials.destroy');

    Route::get('/workspaces/{workspace}/settings/notifications', [NotificationSettingsController::class, 'edit'])
        ->name('notifications.edit');
    Route::put('/workspaces/{workspace}/settings/notifications', [NotificationSettingsController::class, 'update'])
        ->name('notifications.update');
});
