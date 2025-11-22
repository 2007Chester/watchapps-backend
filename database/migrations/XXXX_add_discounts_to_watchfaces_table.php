<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('watchfaces', function (Blueprint $table) {
            $table->integer('discount_price')->nullable()->after('price');
            $table->dateTime('discount_end_at')->nullable()->after('discount_price');
        });
    }

    public function down(): void
    {
        Schema::table('watchfaces', function (Blueprint $table) {
            $table->dropColumn('discount_price');
            $table->dropColumn('discount_end_at');
        });
    }
};
