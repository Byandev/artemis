<?php

use Illuminate\Support\Facades\Route;
use Modules\EscTracker\Http\Controllers\Api\AuthController;
use Modules\EscTracker\Http\Controllers\Api\DailyEscRecordController;
use Modules\EscTracker\Http\Controllers\Api\EscNotificationController;

/*
|--------------------------------------------------------------------------
| EscTracker API routes (WellSync app)
|--------------------------------------------------------------------------
|
| The module's RouteServiceProvider already applies the `api` middleware, an
| `api` URL prefix and an `api.` route-name prefix — so the paths below resolve
| to /api/v1/public/esc/... with names api.v1.public.esc.*
|
| No workspace API key here: users authenticate as themselves with a Sanctum
| token, and every record belongs to the token owner, so a caller can only ever
| read or write their own.
|
*/
Route::group(['prefix' => 'v1/public/esc', 'as' => 'v1.public.esc.'], function () {
    // Login exchanges email + password for a token. Throttled so it can't be
    // used as a password-guessing oracle.
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:public-api-login')
        ->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        // Profile/summary: the token owner plus their ESC streaks, so the app's
        // streak header doesn't depend on having just saved a record.
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');

        Route::get('/daily-records', [DailyEscRecordController::class, 'index'])->name('daily-records.index');
        // Today's record, for the "edit today" screen. Registered before the
        // collection POST so `/today` is never mistaken for a record payload.
        Route::get('/daily-records/today', [DailyEscRecordController::class, 'today'])->name('daily-records.today');
        // Partial update: only the fields sent are touched, so saving one toggle
        // won't blank the notes already stored.
        Route::match(['put', 'patch'], '/daily-records/today', [DailyEscRecordController::class, 'updateToday'])->name('daily-records.today.update');
        Route::post('/daily-records', [DailyEscRecordController::class, 'store'])->name('daily-records.store');

        // Reminder settings for the token owner. PUT and PATCH behave the same —
        // every field is optional, so a partial body is a partial update.
        Route::get('/notifications', [EscNotificationController::class, 'show'])->name('notifications.show');
        Route::match(['put', 'patch'], '/notifications', [EscNotificationController::class, 'update'])->name('notifications.update');
    });
});
