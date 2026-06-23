<?php

use Illuminate\Support\Facades\Route;
use Modules\GencysERP\Http\Controllers\GencysERPController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('gencyserps', GencysERPController::class)->names('gencyserp');
});
