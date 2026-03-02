<?php

use Illuminate\Support\Facades\Route;
use Modules\Pancake\Http\Controllers\PancakeController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('pancakes', PancakeController::class)->names('pancake');
});
