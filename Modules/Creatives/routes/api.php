<?php

use Illuminate\Support\Facades\Route;
use Modules\Creatives\Http\Controllers\CreativesController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('creatives', CreativesController::class)->names('creatives');
});
