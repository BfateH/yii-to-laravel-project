<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Currency\ExchangeRateController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ShopogolicTestController;
use App\Http\Controllers\TestAcquiringController;
use App\Http\Controllers\WebhookController;
use App\Http\Resources\User\AuthUserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/shopogolic/test', [ShopogolicTestController::class, 'testGet'])
    ->name('api.shopogolic.test');

Route::prefix('test')->group(function () {
    Route::get('/test-acquiring/create-payment', [TestAcquiringController::class, 'createTestPayment']);
    Route::get('/test-acquiring/check-status', [TestAcquiringController::class, 'checkPaymentStatus']);
    Route::get('/test-acquiring/refund', [TestAcquiringController::class, 'testRefund']);
});

Route::prefix('webhooks')->group(function () {
    Route::post('/tinkoff', [WebhookController::class, 'handleTinkoff'])->name('webhook.tinkoff');

    Route::post('/alerts/telegram', [WebhookController::class, 'handleTelegramAlert'])->name('webhook.alerts.telegram');
});


// Получение валют
Route::prefix('exchange-rates')->group(function () {
    Route::get('/convert', [ExchangeRateController::class, 'convert']);
    Route::get('/history', [ExchangeRateController::class, 'history']);
});

Route::post('/auth/token-login', [AuthController::class, 'tokenLogin'])
    ->middleware('throttle:10,1')
    ->name('auth.token-login');

Route::post('/auth/tokens', [AuthenticatedSessionController::class, 'loginApi'])
    ->name('auth.tokens.store');

// Основное взаимодействие с api
Route::middleware(['multiAuth', 'checkAccountStatus'])->group(function () {
    Route::get('/auth/tokens/logout', [AuthenticatedSessionController::class, 'logoutApi'])->name('auth.tokens.delete');
    Route::get('/logout', [AuthController::class, 'logout']);

    // Для тестирования токенов, потом удалим
    Route::get('/me', function (Request $request) {
        return AuthUserResource::make($request->user())->resolve();
    });
});


