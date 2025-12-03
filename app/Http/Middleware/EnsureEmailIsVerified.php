<?php

namespace App\Http\Middleware;

use Closure;

class EnsureEmailIsVerified
{
    public function handle($request, Closure $next)
    {
        if (!$request->user() || !$request->user()->email_verified_at) {
            return response()->json([
                'message' => 'Email не подтверждён.',
                'verified' => false,
            ], 403);
        }

        return $next($request);
    }
}
