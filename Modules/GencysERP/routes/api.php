<?php

use Illuminate\Support\Facades\Route;
use Modules\GencysERP\Http\Controllers\GencysERPController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('gencyserps', GencysERPController::class)->names('gencyserp');
});
