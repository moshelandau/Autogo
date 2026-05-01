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
    Route::post('login', [\App\Http\Controllers\Api\WorkerController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout',                                   [\App\Http\Controllers\Api\WorkerController::class, 'logout']);

        // Reservations — list + detail + workflow steps
        Route::get ('reservations',                             [\App\Http\Controllers\Api\WorkerController::class, 'reservations']);
        Route::get ('reservations/{reservation}',               [\App\Http\Controllers\Api\WorkerController::class, 'show']);
        Route::get ('reservations/{reservation}/swap-options',  [\App\Http\Controllers\Api\WorkerController::class, 'swapOptions']);
        Route::post('reservations/{reservation}/swap-vehicle',  [\App\Http\Controllers\Api\WorkerController::class, 'swapVehicle']);
        Route::post('reservations/{reservation}/inspection',    [\App\Http\Controllers\Api\WorkerController::class, 'uploadInspection']);
        Route::post('reservations/{reservation}/sign',          [\App\Http\Controllers\Api\WorkerController::class, 'sign']);
        Route::post('reservations/{reservation}/pickup',        [\App\Http\Controllers\Api\WorkerController::class, 'pickup']);
        Route::post('reservations/{reservation}/return',        [\App\Http\Controllers\Api\WorkerController::class, 'returnVehicle']);

        // Additional drivers (per-reservation)
        Route::post  ('reservations/{reservation}/additional-drivers',          [\App\Http\Controllers\Api\WorkerController::class, 'addAdditionalDriver']);
        Route::delete('reservations/{reservation}/additional-drivers/{driver}', [\App\Http\Controllers\Api\WorkerController::class, 'removeAdditionalDriver']);

        // Customer-level captures (DL + insurance proof)
        Route::post('customers/{customer}/dl',        [\App\Http\Controllers\Api\WorkerController::class, 'uploadDriverLicense']);
        Route::post('customers/{customer}/insurance', [\App\Http\Controllers\Api\WorkerController::class, 'uploadInsurance']);
    });
});

// Telebroad inbound SMS webhook (auth via shared-secret query param)
// Accepts an optional trigger-name suffix (e.g. /Account-SMS) per Telebroad's URL convention.
Route::post('telebroad/webhook/sms/{suffix?}', [\App\Http\Controllers\TelebroadWebhookController::class, 'sms'])
    ->where('suffix', '.*');
