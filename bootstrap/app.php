<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Configuration\Exceptions;

return Application::configure(dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->group('api', [
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
        
        // Регистрация middleware для ролей
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
        
        // Настройка аутентификации для API: возвращать JSON вместо редиректа
        $middleware->redirectGuestsTo(function ($request) {
            // Для API-запросов возвращаем null (не редиректим)
            if ($request->expectsJson() || $request->is('api/*')) {
                return null;
            }
            // Для web-запросов можно вернуть маршрут логина, если он есть
            return '/login';
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Для API-запросов возвращаем JSON вместо редиректа при неаутентификации
        $exceptions->shouldRenderJsonWhen(function ($request, Throwable $e) {
            return $request->expectsJson() || $request->is('api/*');
        });
    })
    ->create();
