<?php

use Illuminate\Support\Facades\Route;
use Modules\TaskManagement\Http\Controllers\TaskController;

Route::middleware(['auth'])->group(function () {
    Route::get('/workspaces/{workspace}/tasks', [TaskController::class, 'index'])->name('workspaces.tasks.index');
    Route::post('/workspaces/{workspace}/tasks', [TaskController::class, 'store'])->name('workspaces.tasks.store');
    Route::put('/workspaces/{workspace}/tasks/{task}', [TaskController::class, 'update'])->name('workspaces.tasks.update');
    Route::delete('/workspaces/{workspace}/tasks/{task}', [TaskController::class, 'destroy'])->name('workspaces.tasks.destroy');
    Route::patch('/workspaces/{workspace}/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('workspaces.tasks.status');
    Route::patch('/workspaces/{workspace}/tasks/{task}/status/all', [TaskController::class, 'updateAllStatuses'])->name('workspaces.tasks.status.all');
    Route::post('/workspaces/{workspace}/tasks/{task}/comments', [TaskController::class, 'storeComment'])->name('workspaces.tasks.comments.store');
});
