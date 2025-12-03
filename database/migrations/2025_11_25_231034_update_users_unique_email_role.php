<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
     public function up()
 {
     Schema::table('users', function (Blueprint $table) {
         // убираем старый UNIQUE(email)
         $table->dropUnique('users_email_unique');

         // создаём новый композитный UNIQUE(email, role)
         $table->unique(['email', 'role']);
     });
 }

 public function down()
 {
     Schema::table('users', function (Blueprint $table) {
         $table->dropUnique(['email', 'role']);
         $table->unique('email');
     });
 }

};
