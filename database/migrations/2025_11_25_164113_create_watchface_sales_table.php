<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watchface_sales', function (Blueprint $table) {
            $table->id();

            $table->foreignId('watchface_id')
                ->constrained()
                ->cascadeOnDelete();

            // Покупатель (позже можно сделать guest-покупки, тогда nullable)
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 10)->default('USD');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchface_sales');
    }
};
