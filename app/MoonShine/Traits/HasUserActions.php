<?php

namespace App\MoonShine\Traits;

use App\Enums\Role;
use App\Services\UserService;
use MoonShine\Laravel\Http\Responses\MoonShineJsonResponse;
use MoonShine\Laravel\MoonShineRequest;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\Support\Enums\ToastType;
use Illuminate\Support\Facades\Log;

trait HasUserActions
{
    public function activate(MoonShineRequest $request): MoonShineJsonResponse
    {
        return $this->handleUserAction(
            $request,
            fn($item) => $this->userService->activate($item),
            __('Пользователь успешно активирован'),
            __('Партнер успешно активирован')
        );
    }

    public function deactivate(MoonShineRequest $request): MoonShineJsonResponse
    {
        return $this->handleUserAction(
            $request,
            fn($item) => $this->userService->deactivate($item),
            __('Пользователь успешно деактивирован'),
            __('Партнер успешно деактивирован')
        );
    }

    public function ban(MoonShineRequest $request): MoonShineJsonResponse
    {
        return $this->handleUserAction(
            $request,
            function ($item) {
                $reason = request()->input('ban_reason');
                $this->userService->ban($item, $reason);
            },
            __('Пользователь успешно заблокирован'),
            __('Партнер успешно заблокирован')
        );
    }

    public function unban(MoonShineRequest $request): MoonShineJsonResponse
    {
        return $this->handleUserAction(
            $request,
            fn($item) => $this->userService->unban($item),
            __('Пользователь успешно разблокирован'),
            __('Партнер успешно разблокирован')
        );
    }

    public function forceDelete(MoonShineRequest $request): MoonShineJsonResponse
    {
        $itemId = $request->getResource()->getItemID();

        if (!$itemId) {
            return MoonShineJsonResponse::make()->toast(__('Сущность не найдена'), ToastType::ERROR);
        }

        $item = $request->getResource()->getModel()->withTrashed()->findOrFail($itemId);

        $checkResult = $this->canForceDeleteItem($item);
        if ($checkResult !== true) {
            return MoonShineJsonResponse::make()
                ->toast(is_string($checkResult) ? $checkResult : __('Невозможно выполнить действие'), ToastType::ERROR);
        }

        try {
            $this->userService->forceDelete($item);
            $tableEventName = "index-table-" . $request->getResource()->getUriKey();
            return MoonShineJsonResponse::make()
                ->toast(__('Сущность и все связанные данные успешно удалены'), ToastType::SUCCESS)
                ->events([AlpineJs::event(JsEvent::TABLE_UPDATED, $tableEventName)]);
        } catch (\Exception $e) {
            Log::error('Force delete error: ' . $e->getMessage());
            return MoonShineJsonResponse::make()
                ->toast(__('Произошла ошибка при удалении'), ToastType::ERROR);
        }
    }

    public function restore(MoonShineRequest $request): MoonShineJsonResponse
    {
        $itemId = $request->getResource()->getItemID();

        if (!$itemId) {
            return MoonShineJsonResponse::make()->toast('Сущность не найдена', ToastType::ERROR);
        }

        $item = $request->getResource()->getModel()->onlyTrashed()->findOrFail($itemId);

        $checkResult = $this->canUpdateItem($item);
        if ($checkResult !== true) {
            return MoonShineJsonResponse::make()
                ->toast(is_string($checkResult) ? $checkResult : __('Невозможно выполнить действие'), ToastType::ERROR);
        }

        try {
            $this->userService->restore($item);
            $tableEventName = "index-table-" . $request->getResource()->getUriKey();

            return MoonShineJsonResponse::make()
                ->toast('Сущность успешно восстановлена', ToastType::SUCCESS)
                ->events([AlpineJs::event(JsEvent::TABLE_UPDATED, $tableEventName)]);
        } catch (\Exception $e) {
            Log::error('Restore error: ' . $e->getMessage());
            return MoonShineJsonResponse::make()
                ->toast(__('Произошла ошибка при восстановлении'), ToastType::ERROR);
        }
    }

    protected function handleUserAction(MoonShineRequest $request, \Closure $actionCallback, string $userMessage, string $partnerMessage): MoonShineJsonResponse
    {
        $item = $request->getResource()->getItem();
        $checkResult = $this->canUpdateItem($item);

        if ($checkResult !== true) {
            return MoonShineJsonResponse::make()
                ->toast(is_string($checkResult) ? $checkResult : __('Невозможно выполнить действие'), ToastType::ERROR);
        }

        try {
            $actionCallback($item);
            $message = $this->isPartnerItem($item) ? $partnerMessage : $userMessage;

            $tableEventName = "index-table-" . $request->getResource()->getUriKey();
            $rowEventName = $tableEventName . "-{$item->id}";

            return MoonShineJsonResponse::make()
                ->toast($message, ToastType::SUCCESS)
                ->events([AlpineJs::event(JsEvent::TABLE_ROW_UPDATED, $rowEventName)]);
        } catch (\Exception $e) {
            Log::error('Entity action error: ' . $e->getMessage());
            return MoonShineJsonResponse::make()
                ->toast(__('Произошла ошибка при выполнении действия'), ToastType::ERROR);
        }
    }

    protected function isPartnerItem(mixed $item): bool
    {
        if (isset($item->role_id)) {
            return $item->role_id === Role::partner->value;
        }

        if (isset($item->moonshineUserRole)) {
            return $item->moonshineUserRole->id === Role::partner->value;
        }

        return false;
    }

    abstract protected function canUpdateItem(mixed $item): bool|string;

    abstract protected function canForceDeleteItem(mixed $item): bool|string;
}
