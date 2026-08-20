<?php

use App\Http\Controllers\GameInputController;
use App\Http\Controllers\PaymentController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/game/input', [GameInputController::class, 'input']);
    Route::post('payments/create', [PaymentController::class, 'createPayment']);
});
Route::post('/payments/yookassa/webhook', [PaymentController::class, 'handleWebhook']);
