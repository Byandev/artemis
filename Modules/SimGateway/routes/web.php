<?php

use Illuminate\Support\Facades\Route;
use Modules\SimGateway\Http\Controllers\Gateway\CallbackController;
use Modules\SimGateway\Http\Middleware\VerifyGatewayCallback;

/*
|--------------------------------------------------------------------------
| Hardware gateway callbacks (YX GP pushes events here)
|--------------------------------------------------------------------------
|
| The device POSTs delivery receipts and inbound SMS to these URLs. They are
| authorised ONLY by a shared token (VerifyGatewayCallback) — never session
| auth — and must be CSRF-exempt (see bootstrap/app.php validateCsrfTokens).
*/

Route::prefix('gateway/callback')->middleware(VerifyGatewayCallback::class)->group(function () {
    Route::post('sms', [CallbackController::class, 'inboundSms'])->name('gateway.callback.sms');
    Route::post('dlr', [CallbackController::class, 'deliveryReport'])->name('gateway.callback.dlr');
});
