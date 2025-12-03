<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

use App\Models\EmailVerificationToken;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * Регистрация: создаём пользователя или добавляем новую роль.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'string', 'email', 'max:255'],
            'password'              => ['required', 'string', 'min:6'],
            'password_confirmation' => ['required', 'same:password'],
            'role'                  => ['required', Rule::in(['user', 'developer', 'admin'])],
        ]);

        $role = $validated['role'];

        // Ищем пользователя по email
        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            // Создаём нового пользователя
            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);
        } else {
            // Обновляем имя
            if ($user->name !== $validated['name']) {
                $user->update(['name' => $validated['name']]);
            }
        }

        // Проверяем, есть ли уже эта роль
        $roleExists = UserRole::where('user_id', $user->id)
            ->where('role', $role)
            ->exists();

        if ($roleExists) {
            return response()->json([
                'message' => 'Эта роль уже привязана к аккаунту.',
            ], 422);
        }

        // ДОБАВЛЯЕМ НОВУЮ РОЛЬ
        $newRole = UserRole::create([
            'user_id' => $user->id,
            'role'    => $role,
        ]);

        if (!$newRole) {
            return response()->json([
                'message' => 'Ошибка при сохранении роли.',
            ], 500);
        }

        // Создаём токен
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => [
                'id'           => $user->id,
                'name'         => $user->name,
                'email'        => $user->email,
                'roles'        => $user->getRoleNames(),
                'primary_role' => $user->primary_role,
                'verified'     => (bool)$user->email_verified_at,
            ],
            'token' => $token,
        ], 201);
    }

    /**
     * Логин
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Неверный email или пароль.',
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => [
                'id'           => $user->id,
                'name'         => $user->name,
                'email'        => $user->email,
                'roles'        => $user->getRoleNames(),
                'primary_role' => $user->primary_role,
                'verified'     => (bool)$user->email_verified_at,
                'email_verified' => (bool)$user->email_verified_at,
                'onboarding_completed' => $user->onboarding_completed,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Текущий пользователь
     */
    public function user(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json([
            'id'           => $user->id,
            'name'         => $user->name,
            'email'        => $user->email,
            'roles'        => $user->getRoleNames(),
            'primary_role' => $user->primary_role,
            'brand_name'   => $user->brand_name,
            'onboarding_completed' => $user->onboarding_completed,
            'verified'     => (bool)$user->email_verified_at,
            'email_verified' => (bool)$user->email_verified_at,
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Logged out']);
    }

    /**
     * Проверка email
     */
    public function checkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'role' => ['required', Rule::in(['user', 'developer', 'admin'])],
        ]);

        $email = $request->email;
        $role = $request->role;

        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'exists' => false,
                'exists_other' => false,
            ]);
        }

        $hasSameRole = UserRole::where('user_id', $user->id)
            ->where('role', $role)
            ->exists();

        if ($hasSameRole) {
            return response()->json([
                'exists' => true,
                'exists_other' => false,
            ]);
        }

        return response()->json([
            'exists' => false,
            'exists_other' => true,
        ]);
    }

    public function sendVerification(Request $request)
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email уже подтверждён'], 422);
        }

        EmailVerificationToken::where('user_id', $user->id)->delete();

        $token = Str::random(64);

        EmailVerificationToken::create([
            'user_id' => $user->id,
            'token' => $token,
        ]);

        $verifyLink = "https://watchapps.ru/verify-email?token={$token}";

        Mail::raw("Перейдите по ссылке чтобы подтвердить email:\n\n{$verifyLink}", function($msg) use ($user) {
            $msg->to($user->email)
                ->subject("Подтверждение email — WatchApps");
        });

        return response()->json(['message' => 'Письмо отправлено']);
    }

    public function verifyEmail($token)
    {
        $record = EmailVerificationToken::where('token', $token)->first();

        if (!$record) {
            return response()->json(['message' => 'Неверный или истекший токен'], 400);
        }

        $user = $record->user;

        $user->email_verified_at = now();
        $user->save();

        $record->delete();

        return response()->json(['message' => 'Email подтверждён!']);
    }

    /**
     * Запрос на восстановление пароля
     */
    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            // Для безопасности не сообщаем, что пользователь не найден
            return response()->json([
                'message' => 'Если email существует, на него будет отправлена ссылка для восстановления пароля.',
            ]);
        }

        // Генерируем токен восстановления
        $token = Str::random(64);
        
        // Сохраняем токен в таблицу password_reset_tokens
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // Формируем ссылку для восстановления
        $resetLink = "https://dev.watchapps.ru/dev/reset-password?token={$token}&email=" . urlencode($user->email);

        // Отправляем email
        Mail::raw("Для восстановления пароля перейдите по ссылке:\n\n{$resetLink}\n\nСсылка действительна в течение 60 минут.", function($msg) use ($user) {
            $msg->to($user->email)
                ->subject("Восстановление пароля — WatchApps");
        });

        return response()->json([
            'message' => 'Если email существует, на него будет отправлена ссылка для восстановления пароля.',
        ]);
    }

    /**
     * Сброс пароля по токену
     */
    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6'],
            'password_confirmation' => ['required', 'same:password'],
        ]);

        // Ищем запись о восстановлении пароля
        $record = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (!$record) {
            return response()->json([
                'message' => 'Неверный или истекший токен восстановления.',
            ], 400);
        }

        // Проверяем, не истек ли токен (60 минут)
        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
            return response()->json([
                'message' => 'Токен восстановления истек. Запросите новую ссылку.',
            ], 400);
        }

        // Проверяем токен
        if (!Hash::check($validated['token'], $record->token)) {
            return response()->json([
                'message' => 'Неверный токен восстановления.',
            ], 400);
        }

        // Обновляем пароль
        $user = User::where('email', $validated['email'])->first();
        if (!$user) {
            return response()->json([
                'message' => 'Пользователь не найден.',
            ], 404);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        // Удаляем использованный токен
        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        return response()->json([
            'message' => 'Пароль успешно изменен.',
        ]);
    }

}
