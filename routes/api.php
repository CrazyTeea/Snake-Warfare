<?php

use App\Http\Controllers\GameInputController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ShopController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/game/input', [GameInputController::class, 'input']);
    Route::post('payments/create', [PaymentController::class, 'createPayment']);
    Route::post('/shop/buy-buff', [ShopController::class, 'buyBuff']);
});
Route::post('/payments/yookassa/webhook', [PaymentController::class, 'handleWebhook']);
