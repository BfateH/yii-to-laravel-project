<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckAccountStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && (!$user->is_active || $user->is_banned)) {

            // Для API-запросов возвращаем JSON
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Ваш аккаунт неактивен или заблокирован.'
                ], 403);
            }

            // Для web-запросов разлогиниваем и редиректим
            Auth::logout();
            return redirect()->route('moonshine.login')
                ->withErrors(['error' => 'Ваш аккаунт неактивен или заблокирован.']);
        }

        return $next($request);
    }
}
