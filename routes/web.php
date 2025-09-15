<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/confirm-delete/{token}', [App\Http\Controllers\AccountController::class, 'confirmDelete'])
    ->name('account.confirm-delete');

// Обычные защищённые web роуты
Route::middleware('auth:moonshine')->group(function () {

});

require __DIR__.'/moonshine.php';
require __DIR__.'/auth.php';
