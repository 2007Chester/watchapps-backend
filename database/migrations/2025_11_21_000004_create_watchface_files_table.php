<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watchface_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('watchface_id')->constrained('watchfaces')->onDelete('cascade');
            $table->foreignId('upload_id')->constrained('uploads')->onDelete('cascade');
            $table->enum('type', ['icon', 'banner', 'screenshot', 'apk']);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchface_files');
    }
};
