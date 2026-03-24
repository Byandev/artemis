<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::get('/leaderboards', function () {
    return Inertia::render('workspaces/public/leaderboard');
})->name('leaderboards.public');

Route::get('/csr-leaderboard', function () {
    return redirect()->route('leaderboards.public');
})->name('csr-leaderboard.public');

Route::get('/auth/facebook/callback', [\App\Http\Controllers\Integrations\FacebookController::class, 'callback']);

Route::middleware(['auth'])->group(function () {

    Route::get('dashboard', function () {
        $user = auth()->user();

        // Get user's first workspace (prioritize owned workspaces)
        $workspace = $user->ownedWorkspaces()->first()
            ?? $user->workspaces()->first();

        // If user has a workspace, redirect to it
        if ($workspace) {
            return redirect()->route('workspace.dashboard', $workspace->slug);
        }

        // If no workspace, redirect to setup
        return redirect()->route('workspaces.setup');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/workspaces.php';
require __DIR__.'/api.php';
