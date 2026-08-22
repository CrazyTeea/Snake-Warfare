<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('game.input', function ($user) {
    return $user !== null;
});

// Добавляем канал комнаты, который использует фронтенд
Broadcast::channel('game.room.{roomCode}', function ($user, $roomCode) {
    return $user !== null; // Проверяем, что пользователь авторизован
});
