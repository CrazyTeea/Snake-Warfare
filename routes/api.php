<?php

use App\Http\Controllers\GameInputController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/game/input', [GameInputController::class, 'input']);
    Route::post('/payments/create', [PaymentController::class, 'createPayment']);
    Route::get('/payments/{transaction}/status', [PaymentController::class, 'checkStatus']);
    Route::post('/shop/buy-buff', [ShopController::class, 'buyBuff']);
});

// Webhook ЮKassa
Route::post('/payments/yookassa/webhook', [PaymentController::class, 'handleWebhook']);
