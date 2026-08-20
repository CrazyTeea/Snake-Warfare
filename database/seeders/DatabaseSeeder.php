<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        Item::updateOrCreate(['slug' => 'shield'], [
            'name' => 'Щит неуязвимости',
            'type' => 'perk',
            'price' => 100,
            'max_uses_per_match' => 3,
            'description' => 'Дает полную неуязвимость на 5 секунд'
        ]);

        Item::updateOrCreate(['slug' => 'invisible'], [
            'name' => 'Невидимость',
            'type' => 'perk',
            'price' => 150,
            'max_uses_per_match' => 2,
            'description' => 'Делает змейку невидимой для других игроков на 5 секунд'
        ]);
    }
}
