<?php

use Illuminate\Support\Facades\Route;
use Modules\MetaAds\Http\Controllers\MetaAdsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('metaads', MetaAdsController::class)->names('metaads');
});
