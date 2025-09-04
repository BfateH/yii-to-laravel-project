<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class MultiAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Попытка аутентификации через JWT
        if ($request->bearerToken() && $this->authenticateJWT($request)) {
            try {
                JWTAuth::parseToken()->authenticate();
            } catch (Exception $e) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            return $next($request);
        }

        // Попытка аутентификации через Sanctum API-ключ
        if ($apiKey = $request->query('api_key') ?? $request->header('X-API-Key')) {
            if ($sanctumUser = $this->authenticateSanctum($apiKey)) {
                Auth::setUser($sanctumUser);
                return $next($request);
            }
        }

        // Попытка аутентификации через сессии (web guard)
        if ($sessionUser = Auth::user()) {
            return $next($request);
        }

        // Если ни один метод не сработал
        return $this->handleUnauthorized($request);
    }

    private function authenticateJWT(Request $request)
    {
        try {
            return JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function authenticateSanctum(string $token)
    {
        $accessToken = PersonalAccessToken::findToken($token);

        if (!$accessToken) {
            return null;
        }

        return $accessToken->tokenable;
    }

    private function handleUnauthorized(Request $request)
    {
        // Для API-запросов возвращаем JSON
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Для web-запросов делаем редирект на страницу логина
        return redirect()->guest(route('moonshine.login'));
    }
}
