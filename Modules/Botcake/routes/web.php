<?php

use Modules\Botcake\Http\Controllers\API\FlowController;
use Modules\Botcake\Http\Controllers\API\SequenceController;

Route::prefix('api/v1/botcake')->name('api.v1.botcake.')->middleware(['auth', 'verified'])->group(function () {
    Route::apiResource('/flows', FlowController::class)->names('flows.index')->only(['index']);
    Route::apiResource('/sequences', SequenceController::class)->names('sequences.index')->only(['index']);
});
