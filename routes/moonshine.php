<?php

use App\Http\Controllers\AccountController;
use Illuminate\Support\Facades\Route;
use \App\MoonShine\Controllers\OrderStatus;

// Защищённые роуты, используемые moonshine
Route::middleware('auth:moonshine')->prefix('moonshine')->group(function () {

    Route::post('/admin/orders/orders-statuses', [OrderStatus::class, 'store'])
        ->name('moonshine.admin.orders.statuses.store');
});


