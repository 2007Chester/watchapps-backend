<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware для проверки ролей пользователя.
 * Использование в routes/api.php:
 *
 * Route::middleware(['auth:sanctum', 'role:developer'])->group(function () {
 *     // только разработчики
 * });
 */
class RoleMiddleware
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  mixed ...$roles список ролей, которые разрешены (developer, user, admin и т.д.)
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Предполагаем, что у модели User есть метод hasAnyRole()
        if (!$user->hasAnyRole($roles)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
