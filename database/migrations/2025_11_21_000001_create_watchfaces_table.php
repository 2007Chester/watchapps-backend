<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watchfaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('developer_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('description')->nullable();
            $table->integer('price')->default(0);
            $table->boolean('is_free')->default(false);
            $table->string('version')->nullable();
            $table->enum('type', ['watchface', 'app'])->default('watchface');
            $table->enum('status', ['draft', 'published', 'hidden'])->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchfaces');
    }
};
