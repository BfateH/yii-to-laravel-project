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

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="Аутентификация и управление токенами JWT"
 * )
 */
class AuthController extends Controller
{
    protected $jtiService;

    public function __construct(JtiService $jtiService)
    {
        $this->jtiService = $jtiService;
    }

    /**
     * Аутентификация по внешнему JWT токену
     *
     * @OA\Post(
     *     path="/auth/token-login",
     *     operationId="authTokenLogin",
     *     tags={"Authentication"},
     *     summary="Аутентификация по внешнему JWT",
     *     description="Принимает JWT от внешнего провайдера, валидирует его и возвращает токен приложения.",
     *     security={},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Данные для аутентификации",
     *         @OA\JsonContent(
     *             required={"provider", "token"},
     *             @OA\Property(property="provider", type="string", example="test", description="Провайдер аутентификации"),
     *             @OA\Property(property="token", type="string", example="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...", description="JWT токен от внешнего провайдера")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Успешная аутентификация",
     *         @OA\JsonContent(
     *             @OA\Property(property="access_token", type="string", description="JWT токен для доступа к API"),
     *             @OA\Property(property="token_type", type="string", example="bearer", description="Тип токена"),
     *             @OA\Property(property="expires_in", type="integer", example=3600, description="Время жизни токена в секундах"),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="provider", type="string", example="test"),
     *                 @OA\Property(property="provider_id", type="string", example="1234567890")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Ошибка аутентификации",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Unauthorized"),
     *             @OA\Property(property="message", type="string", example="Authentication failed. Please try again.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Ошибка валидации",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="provider",
     *                     type="array",
     *                     @OA\Items(type="string", example="The provider field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="token",
     *                     type="array",
     *                     @OA\Items(type="string", example="The token field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Слишком много запросов",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Too Many Attempts.")
     *         )
     *     )
     * )
     */
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
            } else {
                throw new \Exception('Jti payload data no exists');
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
                return redirect()->intended(route('moonshine.index'));
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
                    'message' => 'Authentication failed. Please try again. ' . $e->getMessage(),
                ], 401);
            } else {
                return redirect()->route('moonshine.login')->withErrors(['sso' => 'SSO authentication failed. Please try again.']);
            }
        }
    }

    /**
     * Выход из системы (аннулирование токена)
     *
     * @OA\Get(
     *     path="/logout",
     *     operationId="authLogout",
     *     tags={"Authentication"},
     *     summary="Выход из системы с JWT",
     *     description="Аннулирует текущий JWT токен пользователя.",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Успешный выход из системы",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Successfully logged out")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Неавторизованный доступ",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized.")
     *         )
     *     )
     * )
     */
    public function logout()
    {
        auth('api')->logout();
        auth('moonshine')->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }
}
