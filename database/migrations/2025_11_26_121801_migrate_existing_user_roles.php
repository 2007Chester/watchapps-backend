<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * В up() переносим значения поля users.role в таблицу user_roles.
     */
    public function up(): void
    {
        // Берём всех пользователей, у которых role не пустая
        $users = DB::table('users')
            ->select('id', 'role')
            ->whereNotNull('role')
            ->where('role', '!=', '')
            ->get();

        foreach ($users as $user) {
            // На всякий случай не дублируем
            $exists = DB::table('user_roles')
                ->where('user_id', $user->id)
                ->where('role', $user->role)
                ->exists();

            if (!$exists) {
                DB::table('user_roles')->insert([
                    'user_id' => $user->id,
                    'role'    => $user->role,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * В down() можно удалить все записи (если откатываем миграцию).
     */
    public function down(): void
    {
        DB::table('user_roles')->truncate();
    }
};
