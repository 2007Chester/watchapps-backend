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
        Schema::table('users', function (Blueprint $table) {
            $table->string('brand_name')->nullable()->after('name');
            $table->unsignedBigInteger('logo_upload_id')->nullable()->after('brand_name');
            $table->boolean('onboarding_completed')->default(false)->after('logo_upload_id');
            
            $table->foreign('logo_upload_id')
                ->references('id')
                ->on('uploads')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['logo_upload_id']);
            $table->dropColumn(['brand_name', 'logo_upload_id', 'onboarding_completed']);
        });
    }
};
