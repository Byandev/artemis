<?php

use Illuminate\Support\Facades\Route;
use Modules\Creatives\Http\Controllers\CreativesController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('creatives', CreativesController::class)->names('creatives');
});
