<?php

use Illuminate\Support\Facades\Route;
use Modules\EscTracker\Http\Controllers\Web\EscTrackerController;

/*
|--------------------------------------------------------------------------
| EscTracker web routes
|--------------------------------------------------------------------------
|
| The workspace-facing ESC Tracker page. `{workspace}` binds by slug (see
| Workspace::getRouteKeyName), matching the other workspace routes.
|
*/
Route::middleware(['auth'])->group(function () {
    Route::get('/workspaces/{workspace}/esc-tracker', [EscTrackerController::class, 'index'])
        ->name('workspaces.esc-tracker.index');
});
