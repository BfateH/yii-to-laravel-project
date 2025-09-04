<?php

use Illuminate\Support\Facades\Route;

Route::middleware('multiAuth')->group(function () {
    Route::post('/admin/orders/orders-statuses', [\App\MoonShine\Controllers\OrderStatus::class, 'store'])->name('admin.orders.statuses.store');
});
