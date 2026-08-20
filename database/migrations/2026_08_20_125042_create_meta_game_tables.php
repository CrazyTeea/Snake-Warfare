<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Добавляем энергию юзерам
        Schema::table('users', function (Blueprint $table) {
            $table->integer('energy')->default(5);
            $table->timestamp('last_energy_update')->nullable();
        });

        // Таблица товаров (скины, оружие, перки)
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // 'skin', 'weapon', 'perk'
            $table->integer('price');
            $table->integer('max_uses_per_match')->default(1);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Инвентарь пользователя (купленное)
        Schema::create('user_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(1);
        });

        // Экипировка на текущий бой (Loadout)
        Schema::create('user_loadouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('slot'); // 'active_skin', 'active_weapon'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_loadouts');
        Schema::dropIfExists('user_inventory');
        Schema::dropIfExists('items');
    }
};
