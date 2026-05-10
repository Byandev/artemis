<?php

use Illuminate\Support\Facades\Route;
use Modules\MetaAds\Http\Controllers\MetaAdsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('metaads', MetaAdsController::class)->names('metaads');
});
