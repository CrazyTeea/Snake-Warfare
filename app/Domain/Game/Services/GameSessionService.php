<?php

namespace App\Domain\Game\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class GameSessionService
{
    /**
     * Формирует наборы абилок на раунд.
     * Пока нет UI инвентаря — автоматически экипирует все доступные баффы пользователя.
     *
     * @return array<string, array{count: int}>
     */
    public function prepareMatchLoadout(User $user): array
    {
        $equipped = [];

        // 1. Проверяем JSON-поле в модели User (если данные хранятся там)
        if (!empty($user->equipped_buffs) && is_array($user->equipped_buffs)) {
            return $user->equipped_buffs;
        }

        // 2. Если используется таблица user_buffs — забираем все баффы пользователя, где количество > 0
        if (Schema::hasTable('user_buffs')) {
            $buffs = DB::table('user_buffs')
                ->where('user_id', $user->id)
                ->where('quantity', '>', 0)
                ->get();

            foreach ($buffs as $buff) {
                $equipped[$buff->type] = [
                    'count' => (int) $buff->quantity,
                ];
            }
        }

        // 3. Запасной дефолт для тестов (если в БД у юзера совсем пусто)
        if (empty($equipped)) {
            $equipped = [
                'shield' => ['count' => 10],
                'invisible' => ['count' => 10],
            ];
        }

        return $equipped;
    }
}
