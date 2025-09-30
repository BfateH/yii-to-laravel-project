<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\TicketMessageController;
use App\Http\Controllers\WebhookController;
use App\MoonShine\Controllers\OrderStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Защищённые роуты, используемые moonshine
Route::middleware('auth:moonshine')->prefix('moonshine')->group(function () {
    Route::get('/admin/telegram/setTelegramWebhook', [WebhookController::class, 'setTelegramWebhook'])->name('moonshine.admin.setTelegramWebhook');

    Route::post('/admin/profile', [AccountController::class, 'store'])
        ->name('moonshine.admin.profile.store');

    Route::post('/admin/orders/orders-statuses', [OrderStatus::class, 'store'])
        ->name('moonshine.admin.orders.statuses.store');

    // Роуты для работы с сообщениями тикетов
    Route::prefix('tickets/{ticket}')->group(function () {
        // Получение списка сообщений тикета
        Route::get('/messages', [TicketMessageController::class, 'index'])
            ->name('moonshine.tickets.messages.index');

        // Создание нового сообщения в тикете
        Route::post('/messages', [TicketMessageController::class, 'store'])
            ->name('moonshine.tickets.messages.store');
    });

    // Роут для загрузки изображений в CKEditor
    Route::post('/ckeditor/upload', function (\Illuminate\Http\Request $request) {
        try {
            $request->validate([
                'upload' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($request->hasFile('upload')) {
                $path = $request->file('upload')->store('images', 'public');
                $url = Storage::url($path);

                return response()->json(['url' => $url]);
            }

            return response()->json(['error' => ['message' => 'No file uploaded']], 400);
        } catch (\Exception $e) {
            Log::error('CKEditor upload error: ' . $e->getMessage());
            return response()->json(['error' => ['message' => 'Upload failed']], 500);
        }
    })->name('ckeditor.upload');

});


