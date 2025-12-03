<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watchface_views', function (Blueprint $table) {
            $table->id();

            // Какой циферблат смотрели
            $table->foreignId('watchface_id')
                ->constrained()
                ->cascadeOnDelete();

            // Кто смотрел (может быть гость)
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // IP + User-Agent для базовой аналитики
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps(); // created_at важен для графиков
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchface_views');
    }
};
