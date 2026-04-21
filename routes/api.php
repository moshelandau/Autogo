<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Telebroad inbound SMS webhook (auth via shared-secret query param)
// Accepts an optional trigger-name suffix (e.g. /Account-SMS) per Telebroad's URL convention.
Route::post('telebroad/webhook/sms/{suffix?}', [\App\Http\Controllers\TelebroadWebhookController::class, 'sms'])
    ->where('suffix', '.*');
