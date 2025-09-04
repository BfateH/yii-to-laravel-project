<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');


// Обычные защищённые web роуты
Route::middleware('multiAuth')->group(function () {

});

require __DIR__.'/moonshine.php';
require __DIR__.'/auth.php';
