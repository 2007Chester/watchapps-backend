<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watchface_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('watchface_id')->constrained('watchfaces')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchface_category');
    }
};
