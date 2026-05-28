<?php

use Illuminate\Support\Facades\Route;
use Modules\TaskManagement\Http\Controllers\TaskManagementController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('taskmanagements', TaskManagementController::class)->names('taskmanagement');
});
