<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Resources\User\AuthUserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//Route::get('/user', function (Request $request) {
//    return $request->user();
//})->middleware('auth:sanctum');


Route::post('/auth/token-login', [AuthController::class, 'tokenLogin'])
    ->middleware('throttle:10,1');

// Здесь все дейвствия по api
Route::middleware('multiAuth')->group(function () {
    Route::get('/logout', [AuthController::class, 'logout']);

    Route::get('/me', function (Request $request) {
        return AuthUserResource::make($request->user())->resolve();
    });
});

