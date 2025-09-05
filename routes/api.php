<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Resources\User\AuthUserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/token-login', [AuthController::class, 'tokenLogin'])
    ->middleware('throttle:10,1')->name('auth.token-login');

Route::post('/auth/tokens', [AuthenticatedSessionController::class, 'loginApi'])->name('auth.tokens.store');

// Основное взаимодействие с api
Route::middleware('multiAuth')->group(function () {
    Route::get('/auth/tokens/logout', [AuthenticatedSessionController::class, 'logoutApi'])->name('auth.tokens.delete');
    Route::get('/logout', [AuthController::class, 'logout']);

    // Для тестирования токенов, потом удалим
    Route::get('/me', function (Request $request) {
        return AuthUserResource::make($request->user())->resolve();
    });
});

