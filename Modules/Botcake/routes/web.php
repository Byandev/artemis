<?php

use Illuminate\Support\Facades\Route;
use Modules\Botcake\Http\Controllers\API\FlowController;
use Modules\Botcake\Http\Controllers\API\SequenceController;
use Modules\Botcake\Http\Controllers\API\SequenceMessageController;

Route::prefix('api/v1/botcake')->name('api.v1.botcake.')->middleware(['auth', 'workspace'])->group(function () {
    Route::apiResource('/flows', FlowController::class)->names('flows.index')->only(['index']);
    Route::apiResource('/sequences', SequenceController::class)->names('sequences.index')->only(['index']);
    Route::apiResource('/sequence-messages', SequenceMessageController::class)->names('sequence-messages.index')->only(['index']);
});
