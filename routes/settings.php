<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
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
});
