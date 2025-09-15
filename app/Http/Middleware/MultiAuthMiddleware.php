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
        if ($request->bearerToken()) {
            try {
                $user = JWTAuth::parseToken()->authenticate();
                if($user) {
                    Auth::guard('moonshine')->setUser($user);
                }
            } catch (Exception $e) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            return $next($request);
        }

        // Попытка аутентификации через Sanctum API-ключ
        if ($apiKey = $request->query('api_key') ?? $request->header('X-API-Key')) {
            if ($sanctumUser = $this->authenticateSanctum($apiKey)) {
                Auth::guard('moonshine')->setUser($sanctumUser);
                return $next($request);
            }
        }

        // Если ни один метод не сработал
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    private function authenticateSanctum(string $token)
    {
        $accessToken = PersonalAccessToken::findToken($token);

        if (!$accessToken) {
            return null;
        }

        return $accessToken->tokenable;
    }
}
