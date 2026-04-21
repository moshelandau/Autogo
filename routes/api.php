<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Telebroad inbound SMS webhook (auth via shared-secret query param)
Route::post('telebroad/webhook/sms', [\App\Http\Controllers\TelebroadWebhookController::class, 'sms']);
