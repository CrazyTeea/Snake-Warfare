<?php

use App\Http\Controllers\GameInputController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/game/input', [GameInputController::class, 'input']);
});
