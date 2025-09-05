<?php

namespace App\Swagger;

/**
 * @OA\Info(
 *     title="API Documentation",
 *     version="1.0.0",
 *     description="Документация API",
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="Основной API сервер"
 * )
 *
 *
 * @OA\Components(
 *     @OA\SecurityScheme(
 *         securityScheme="apiKeyAuth",
 *         type="apiKey",
 *         in="header",
 *         name="X-API-KEY",
 *         description="API Key для аутентификации"
 *     ),
 *     @OA\SecurityScheme(
 *         securityScheme="bearerAuth",
 *         type="http",
 *         scheme="bearer",
 *         bearerFormat="JWT",
 *         description="Аутентификация по JWT токену"
 *     ),
 *     @OA\Schema(
 *         schema="Error",
 *         type="object",
 *         @OA\Property(
 *             property="message",
 *             type="string",
 *             example="Произошла ошибка."
 *         ),
 *         @OA\Property(
 *             property="errors",
 *             type="object",
 *             @OA\Property(
 *                 property="field",
 *                 type="array",
 *                 @OA\Items(type="string", example="Ошибка валидации для поля.")
 *             )
 *         )
 *     ),
 *     @OA\Schema(
 *         schema="Success",
 *         type="object",
 *         @OA\Property(
 *             property="message",
 *             type="string",
 *             example="Успешная операция."
 *         ),
 *         @OA\Property(
 *             property="data",
 *             type="object",
 *             example={}
 *         )
 *     ),
 *     @OA\Schema(
 *         schema="User",
 *         type="object",
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="role_id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="John Doe"),
 *         @OA\Property(property="email", type="string", example="john@example.com"),
 *         @OA\Property(property="avatar", type="string", example="")
 *     ),
 *     @OA\Schema(
 *         schema="AuthResponse",
 *         type="object",
 *         @OA\Property(property="access_token", type="string", description="JWT токен для доступа к API"),
 *         @OA\Property(property="token_type", type="string", example="bearer", description="Тип токена"),
 *         @OA\Property(property="expires_in", type="integer", example=3600, description="Время жизни токена в секундах"),
 *         @OA\Property(property="user", ref="#/components/schemas/User")
 *     ),
 *     @OA\Schema(
 *         schema="LoginRequest",
 *         type="object",
 *         required={"provider", "token"},
 *         @OA\Property(property="provider", type="string", example="google", description="Провайдер аутентификации"),
 *         @OA\Property(property="token", type="string", example="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...", description="JWT токен от внешнего провайдера")
 *     )
 * )
 *
 *
 * @OA\Tag(
 *     name="Authentication",
 *     description="Аутентификация и управление токенами JWT"
 * )
 *
 * @OA\Tag(
 *    name="Sanctum Token Management",
 *    description="Управление API токенами Sanctum (Наши токены)"
 * )
 *
 * @OA\OpenApi(
 *     security={
 *         {"bearerAuth": {}}
 *     }
 * )
 */
class SwaggerConfig
{
    // Этот класс служит только для размещения главной аннотации Swagger.
    // Он не должен содержать никакой логики.
}
