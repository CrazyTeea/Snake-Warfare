<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class GrantTestBuffsCommand extends Command
{
    protected $signature = 'game:grant-buffs {user_id=1} {count=10}';
    protected $description = 'Начислить и экипировать щиты и невидимость пользователю для тестов';

    public function handle(): int
    {
        $userId = (int) $this->argument('user_id');
        $count = (int) $this->argument('count');

        $user = User::find($userId);

        if (!$user) {
            $this->error("Пользователь с ID {$userId} не найден.");
            return self::FAILURE;
        }

        // 1. Выдача через БД (таблица user_buffs / inventory)
        if (\Schema::hasTable('user_buffs')) {
            DB::table('user_buffs')->updateOrInsert(
                ['user_id' => $user->id, 'type' => 'shield'],
                ['quantity' => $count, 'is_equipped' => true]
            );
            DB::table('user_buffs')->updateOrInsert(
                ['user_id' => $user->id, 'type' => 'invisible'],
                ['quantity' => $count, 'is_equipped' => true]
            );
        }

        // 2. Выдача через JSON-поле в модели User (если используется)
        if (\Schema::hasColumn('users', 'equipped_buffs')) {
            $user->update([
                'equipped_buffs' => [
                    'shield' => ['count' => $count],
                    'invisible' => ['count' => $count],
                ],
            ]);
        }

        $this->info("Успешно выдано по {$count} баффов 'shield' и 'invisible' пользователю {$user->name} (ID: {$userId})!");

        return self::SUCCESS;
    }
}
