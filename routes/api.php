<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// AutoGo Worker (Android) — Sanctum personal access tokens.
// The native worker app signs in once with email + password and gets a
// long-lived bearer token stored in DataStore on the phone. Every
// subsequent request carries Authorization: Bearer <token>.
Route::prefix('worker')->group(function () {
    Route::post('login',  [\App\Http\Controllers\Api\WorkerController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout',                         [\App\Http\Controllers\Api\WorkerController::class, 'logout']);
        Route::get ('reservations',                   [\App\Http\Controllers\Api\WorkerController::class, 'reservations']);
        Route::post('reservations/{reservation}/inspection', [\App\Http\Controllers\Api\WorkerController::class, 'uploadInspection']);
    });
});

// Telebroad inbound SMS webhook (auth via shared-secret query param)
// Accepts an optional trigger-name suffix (e.g. /Account-SMS) per Telebroad's URL convention.
Route::post('telebroad/webhook/sms/{suffix?}', [\App\Http\Controllers\TelebroadWebhookController::class, 'sms'])
    ->where('suffix', '.*');
