<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Создаём таблицу user_roles для хранения ролей пользователей.
     * Один пользователь может иметь несколько ролей: user, developer, admin и т.п.
     */
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();

            // Связь с users
            $table->unsignedBigInteger('user_id');

            // Название роли: user, developer, admin
            $table->string('role', 50);

            $table->timestamps();

            // Внешний ключ
            $table
                ->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            // Один и тот же пользователь не может иметь одну и ту же роль дважды
            $table->unique(['user_id', 'role']);
        });
    }

    /**
     * Откат миграции.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
