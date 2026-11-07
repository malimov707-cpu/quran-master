<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->is_active) {
            $token = $user->currentAccessToken();
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }
            if ($request->hasSession()) {
                auth('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => __('Akkaunt faol emas.')], 401);
            }

            return redirect()->route('login')->withErrors(['email' => __('Akkaunt faol emas.')]);
        }

        return $next($request);
    }
}
