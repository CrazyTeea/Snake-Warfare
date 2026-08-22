<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\TelegramAuthController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GameInputController;
use App\Http\Controllers\LobbyController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Главная / Домашняя страница
Route::get('/', function () {
    return Auth::check() ? redirect()->route('lobby.index') : Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('home');

Route::get('/auth/telegram/callback', [TelegramAuthController::class, 'handleCallback'])->name('auth.telegram');

// Маршруты для гостей (Guest)
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

// Защищенные маршруты (Auth)
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/lobby', [LobbyController::class, 'index'])->name('lobby.index');
    Route::post('/lobby', [LobbyController::class, 'store'])->name('lobby.store');
    Route::get('/lobby/{code}', [LobbyController::class, 'show'])->name('lobby.show');
    Route::post('/lobby/{code}/start', [LobbyController::class, 'start'])->name('lobby.start');

    // === ИГРА ===[cite: 46]
    Route::get('/game/room/{code}', [GameController::class, 'room'])->name('game.room');
    Route::post('/game/spawn', [GameController::class, 'spawn'])->name('game.spawn');

    // Этот роут вызывается из твоего Vue компонента для отправки координат и стейта[cite: 48]
    Route::post('/game/input', [GameController::class, 'input'])->name('game.input');
    // Callback редирект ЮKassa (Inertia рендер)
    Route::get('/payments/callback/{transaction}', [PaymentController::class, 'callback'])->name('payments.callback');
});
