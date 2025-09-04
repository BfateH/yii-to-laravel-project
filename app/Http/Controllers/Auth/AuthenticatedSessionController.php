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

    public function logoutApi(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = request()->user();

        if ($user) {
            $user->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Вы вышли из системы']);
    }
}
