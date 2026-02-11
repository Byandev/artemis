<?php

use Illuminate\Support\Facades\Route;
use Modules\AdsManager\Http\Controllers\API\CampaignController;

Route::prefix('api/v1/ads-manager')->name('api.v1.ads-manager.')->middleware(['auth', 'verified'])->group(function () {
    Route::apiResource('/campaigns', CampaignController::class)->names('campaigns');
});
