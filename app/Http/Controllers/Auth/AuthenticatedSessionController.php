<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\User\AuthUserResource;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        return inertia_location(moonshineRouter()->to('login'));
    }

    public function destroy(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        return inertia_location(moonshineRouter()->to('logout'));
    }

    /**
     * @OA\Post(
     *     path="/auth/tokens",
     *     operationId="loginApi",
     *     tags={"Sanctum Token Management"},
     *     summary="Получение токена аутентификации Sanctum",
     *     description="Аутентификация пользователя по email и паролю и выдача API токена Sanctum.",
     *     security={},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Данные для аутентификации",
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Успешная аутентификация",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string", description="Sanctum API токен"),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="user@example.com")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Неверные учетные данные",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Неверные учётные данные")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Ошибка валидации",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function loginApi(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::query()->where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Неверные учётные данные',
            ], 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => AuthUserResource::make($user)->resolve(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/auth/tokens/logout",
     *     operationId="logoutApi",
     *     tags={"Sanctum Token Management"},
     *     summary="Выход из системы и удаление токенов Sanctum",
     *     description="Аннулирует все API токены Sanctum текущего пользователя. Для аутентификации требуется API-ключ Sanctum (X-API-Key header или api_key query parameter).",
     *     security={{"apiKeyAuth": {}}},
     *     @OA\Parameter(
     *         name="api_key",
     *         in="query",
     *         description="API ключ Sanctum (альтернатива X-API-Key header)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Успешный выход из системы",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Вы вышли из системы")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Неавторизованный доступ",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function logoutApi(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = request()->user();

        if ($user) {
            $user->tokens()->delete();
        }

        return response()->json(['message' => 'Вы вышли из системы']);
    }
}
