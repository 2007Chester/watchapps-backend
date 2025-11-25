<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Регистрация пользователя
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:6',
            'name'     => 'nullable|string|max:255',
            'role'     => 'nullable|in:developer,user,admin'
        ]);

        $role = $validated['role'] ?? 'user';

        $user = User::create([
            'name'     => $validated['name'] ?? '',
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role'     => $role,
        ]);

        // Создаём токен
        $token = $user->createToken('auth_token')->plainTextToken;

        // Ставим httpOnly cookie
        return response()
            ->json(['user' => $user])
            ->cookie('wa_token', $token, 60 * 24 * 7, '/', null, true, true, false, 'Strict');
    }

    /**
     * Логин
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'    => 'required',
            'password' => 'required'
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()
            ->json(['user' => $user])
            ->cookie('wa_token', $token, 60 * 24 * 7, '/', null, true, true, false, 'Strict');
    }

    /**
     * Получить текущего пользователя
     */
    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()
            ->json(['message' => 'Logged out'])
            ->cookie('wa_token', '', -1);
    }
}
