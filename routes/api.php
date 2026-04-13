<?php

Route::group(['prefix' => 'v1/public', 'as' => 'api.v1.public.', 'middleware' => ['api.key']], function () {
    Route::get('/health',             \App\Http\Controllers\PublicApi\HealthController::class)->name('health');
    Route::get('/users',              [\App\Http\Controllers\PublicApi\UserController::class, 'index'])->name('users.index');
    Route::post('/csr-daily-records', [\App\Http\Controllers\PublicApi\CsrDailyRecordController::class, 'store'])->name('csr-daily-records.store');
});
