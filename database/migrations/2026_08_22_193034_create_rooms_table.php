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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('max_players')->default(20);
            $table->boolean('is_private')->default(false);
            $table->string('password')->nullable();
            $table->enum('status', ['waiting', 'playing', 'finished'])->default('waiting');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
