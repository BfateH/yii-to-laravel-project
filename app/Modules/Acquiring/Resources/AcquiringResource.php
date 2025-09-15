<?php

namespace App\Modules\Acquiring\Resources;

use App\Models\Payment;
use App\Modules\Acquiring\Enums\AcquirerType;
use App\Modules\Acquiring\Enums\PaymentStatus;
use Illuminate\Support\Facades\Auth;
use MoonShine\Laravel\Enums\Ability;
use MoonShine\Laravel\Enums\Action;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

class AcquiringResource extends ModelResource
{
    protected string $model = Payment::class;

    public function getTitle(): string
    {
        return __('Платежи');
    }

    public function isCan(Ability $ability): bool
    {
        $user = Auth::user();

        if (!$user->isAdminRole()) {
            return false;
        }

        return parent::isCan($ability);
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->only(Action::VIEW);
    }

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Пользователь', 'user.email'),
            Number::make('Сумма', 'amount'),
            Text::make('Валюта', 'currency')->sortable(),
            Enum::make('Статус', 'status')->attach(PaymentStatus::class)->sortable(),
            Enum::make('Эквайринг', 'acquirer_type')->attach(AcquirerType::class)->sortable(),
            Text::make('ID заказа партнера', 'order_id')->sortable(),
            Date::make('Создан', 'created_at'),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            Text::make('Пользователь', 'user.email'),
            Number::make('Сумма', 'amount'),
            Text::make('Валюта', 'currency'),
            Enum::make('Статус', 'status')->attach(PaymentStatus::class),
            Enum::make('Эквайринг', 'acquirer_type')->attach(AcquirerType::class),
            Text::make('ID платежа у эквайринга', 'acquirer_payment_id'),
            Text::make('ID заказа партнера', 'order_id'),
            Text::make('Описание', 'description'),
            Text::make('Ключ идемпотентности', 'idempotency_key'),
            Text::make('ID лога вебхука', 'webhook_log_id'),
            Date::make('Создан', 'created_at'),
            Date::make('Обновлен', 'updated_at'),
            Json::make('Метаданные', 'metadata'),
        ];
    }

    protected function formFields(): iterable
    {
        return [];
    }

    public function rules($item): array
    {
        return [];
    }

    public function search(): array
    {
        return [
            'id',
            'status',
            'user.email',
            'acquirer_type',
            'order_id'
        ];
    }

    public function filters(): array
    {
        return [
            Enum::make('Статус', 'status')->attach(PaymentStatus::class)->nullable(),
            Enum::make('Эквайринг', 'acquirer_type')->attach(AcquirerType::class)->nullable(),
            Text::make('ID платежа у эквайринга', 'acquirer_payment_id'),
            Text::make('ID заказа партнера', 'order_id'),
        ];
    }

    public function actions(): array
    {
        return [];
    }

    public function redirectAfterSave(): ?string
    {
        return null;
    }
}
