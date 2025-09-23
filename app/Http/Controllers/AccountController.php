<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileFormRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use MoonShine\Laravel\Contracts\Notifications\MoonShineNotificationContract;
use MoonShine\Laravel\Http\Controllers\MoonShineController;
use MoonShine\Laravel\Pages\ProfilePage;
use MoonShine\Support\Enums\ToastType;
use Symfony\Component\HttpFoundation\Response;

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

    public function store(ProfileFormRequest $request): Response
    {
        $page = moonshineConfig()->getPage('profile', ProfilePage::class);
        $form = $page->getForm();
        $form->excludeFields($this->getNotificationFieldNames());

        $success = $form->apply(
            static fn (Model $item) => $item->save(),
        );

        if ($success) {
            $this->handleNotificationSubscriptions($request);
        }

        $message = $success ? __('moonshine::ui.saved') : __('moonshine::ui.saved_error');
        $type = $success ? ToastType::SUCCESS : ToastType::ERROR;

        if ($request->ajax()) {
            return $this->json(message: $message, messageType: $type);
        }

        $this->toast(
            __('moonshine::ui.saved'),
            $type
        );

        return back();
    }

    protected function getNotificationFieldNames(): array
    {
        $fieldNames = [];
        $channels = \App\Models\Channel::all();

        foreach ($channels as $channel) {
            $fieldNames[] = 'alert_' . $channel->id;
        }

        return $fieldNames;
    }

    protected function handleNotificationSubscriptions(ProfileFormRequest $request): void
    {
        $user = Auth::user();

        if (!$user) {
            return;
        }

        $channels = \App\Models\Channel::all();

        foreach ($channels as $channel) {
            $fieldName = 'alert_' . $channel->id;

            if ($request->has($fieldName)) {
                $shouldBeSubscribed = (bool) $request->input($fieldName);
                $subscription = \App\Models\Subscription::firstOrNew([
                    'user_id' => $user->id,
                    'channel_id' => $channel->id
                ]);

                $subscription->subscribed = $shouldBeSubscribed;
                $subscription->save();
            }
        }
    }

}
