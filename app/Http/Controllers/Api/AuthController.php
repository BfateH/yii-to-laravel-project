<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TokenLoginRequest;
use App\Http\Resources\User\AuthUserResource;
use App\Models\User;
use App\Services\ExternalJwtValidatorService;
use App\Services\JtiService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    protected $jtiService;

    public function __construct(JtiService $jtiService)
    {
        $this->jtiService = $jtiService;
    }

    public function tokenLogin(TokenLoginRequest $request)
    {
        $provider = $request->input('provider');
        $token = $request->input('token');

        Log::info('SSO JWT authentication attempt', [
            'provider' => $provider,
            'token_abbr' => substr($token, 0, 20) . '...',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        try {
            // Валидация внешнего JWT
            $jwtValidator = new ExternalJwtValidatorService($provider);
            $validatedPayload = $jwtValidator->validateToken($token);

            Log::debug('JWT payload validated', [
                'provider' => $provider,
                'payload_keys' => array_keys((array)$validatedPayload),
                'has_email' => !empty($validatedPayload->email),
                'has_name' => !empty($validatedPayload->name),
                'has_sub' => !empty($validatedPayload->sub),
                'has_jti' => !empty($validatedPayload->jti)
            ]);

            // Защита от replay атак
            if (isset($validatedPayload->jti)) {
                if ($this->jtiService->isAlreadyUsed($validatedPayload->jti, $provider)) {
                    Log::error('JTI replay attack detected', [
                        'provider' => $provider,
                        'jti' => $validatedPayload->jti,
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent()
                    ]);

                    throw new \Exception('Token has already been used');
                }

                // Помечаем JTI как использованный
                $ttl = isset($validatedPayload->exp)
                    ? max(300, $validatedPayload->exp - time()) // мин. 5 минут
                    : 3600; // 1 час по умолчанию

                $this->jtiService->markAsUsed($validatedPayload->jti, $provider, $ttl);

                Log::debug('JTI marked as used', [
                    'provider' => $provider,
                    'jti' => $validatedPayload->jti,
                    'ttl_seconds' => $ttl
                ]);
            }

            // Маппинг полей из payload
            $email = $validatedPayload->email ?? null;
            $name = $validatedPayload->name ?? null;
            $providerId = $validatedPayload->sub ?? null;

            if (!$email || (filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
                Log::warning('Invalid or missing email in JWT payload', [
                    'provider' => $provider,
                    'email_received' => $email,
                    'has_jti' => !empty($validatedPayload->jti)
                ]);
                throw new \Exception("Valid email is required for authentication");
            }

            // Поиск или создание пользователя
            $user = User::query()->firstOrNew(['email' => $email]);

            if (!$user->exists) {
                Log::info('Creating new user via SSO JWT', [
                    'provider' => $provider,
                    'email' => $email,
                    'name' => $name,
                    'provider_id' => $providerId,
                    'has_jti' => !empty($validatedPayload->jti)
                ]);

                $user->name = $name;
                $user->provider_id = $providerId;
                $user->password = Hash::make(uniqid());
            } else {
                Log::info('Updating existing user via SSO JWT', [
                    'user_id' => $user->id,
                    'provider' => $provider,
                    'email' => $email,
                    'name_updated' => !empty($name),
                    'provider_id_updated' => !empty($providerId),
                    'has_jti' => !empty($validatedPayload->jti)
                ]);

                // Обновление данных пользователя
                if ($name) {
                    $user->name = $name;
                }

                if ($providerId) {
                    $user->provider_id = $providerId;
                }
            }

            $user->provider = $provider;
            $user->save();

            // Генерация JWT токена с помощью Tymon/JWT-Auth
            $authToken = JWTAuth::fromUser($user);

            Log::info('SSO JWT authentication successful', [
                'provider' => $provider,
                'user_id' => $user->id,
                'email' => $user->email,
                'new_user' => !$user->wasRecentlyCreated,
                'token_length' => strlen($authToken),
                'jti_protected' => !empty($validatedPayload->jti)
            ]);

            // Формирование ответа
            $user->refresh();

            if ($request->expectsJson()) {
                return response()->json([
                    'access_token' => $authToken,
                    'token_type' => 'bearer',
                    'expires_in' => auth('api')->factory()->getTTL() * 60,
                    'user' => AuthUserResource::make($user)->resolve(),
                ]);
            } else {
                // Для веб-клиентов
                auth()->login($user);
                return redirect()->intended('/dashboard');
            }

        } catch (\Exception $e) {
            Log::error('SSO JWT authentication failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'has_jti' => isset($validatedPayload->jti) ?? false
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Authentication failed. Please try again.',
                ], 401);
            } else {
                return redirect()->route('login')->withErrors(['sso' => 'SSO authentication failed. Please try again.']);
            }
        }
    }

    public function logout()
    {
        $user = auth('api')->user();

        Log::info('User logout', [
            'user_id' => $user->id ?? null,
            'email' => $user->email ?? null,
            'ip' => request()->ip()
        ]);

        auth('api')->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }
}
