<?php

use Illuminate\Support\Facades\Route;
use Modules\Pancake\Http\Controllers\PancakeController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('pancakes', PancakeController::class)->names('pancake');
});
