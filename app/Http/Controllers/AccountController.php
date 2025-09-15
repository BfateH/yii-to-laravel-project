<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\Log;
use MoonShine\Laravel\Contracts\Notifications\MoonShineNotificationContract;
use MoonShine\Laravel\Http\Controllers\MoonShineController;

class AccountController extends MoonShineController
{
    protected UserService $userService;

    public function __construct(
        MoonShineNotificationContract $notification,
        UserService $userService
    ) {
        parent::__construct($notification);
        $this->userService = $userService;
    }

    public function confirmDelete($token): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
    {
        $user = User::query()
            ->where('delete_confirmation_token', $token)
            ->where('delete_requested_at', '>', now()->subHours(24))
            ->first();

        if (!$user) {
            return redirect(route('moonshine.login'))
                ->withErrors(['error' => 'Ссылка на удаление аккаунта не действительна.']);
        }

        try {
            $this->userService->forceDelete($user);
            return redirect(route('moonshine.login'))
                ->with('success', 'Аккаунт успешно удален.');
        } catch (\Exception $e) {
            Log::error('Error force deleting user: ' . $e->getMessage());
            return redirect(route('moonshine.login'))
                ->withErrors(['error' => 'Произошла ошибка при удалении аккаунта.']);
        }
    }
}
