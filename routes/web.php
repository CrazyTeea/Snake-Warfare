<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\TelegramAuthController;
use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Главная / Домашняя страница
Route::get('/', function () {
    return Auth::check() ? redirect()->route('game.index') : Inertia::render('Welcome', [
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
    //Route::redirect('/', '/game');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/game', [GameController::class, 'index'])->name('game.index');
    Route::post('/game/spawn', [GameController::class, 'spawn'])->name('game.spawn');
});
