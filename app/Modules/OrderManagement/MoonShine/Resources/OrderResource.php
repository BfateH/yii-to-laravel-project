<?php

declare(strict_types=1);

namespace App\Modules\OrderManagement\MoonShine\Resources;

use App\Modules\OrderManagement\Enums\OrderStatus;
use App\Modules\OrderManagement\Models\Order;
use App\Modules\OrderManagement\MoonShine\Pages\Orders\OrderStatuses;

use Illuminate\Validation\Rule;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Enum;
/**
 * @extends ModelResource<Order>
 */
class OrderResource extends ModelResource
{
    protected string $model = Order::class;
    protected string $title = 'Заказы';

    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Enum::make('Статус', 'status')->attach(OrderStatus::class)
        ];
    }

    /**
     * @return FieldContract
     */
    protected function formFields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                Enum::make('Статус', 'status')->attach(OrderStatus::class)
            ])
        ];
    }

    protected function pages(): array
    {
        return [
            ...parent::pages(),
            OrderStatuses::class
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            Enum::make('Статус', 'status')->attach(OrderStatus::class)
        ];
    }


    protected function topButtons(): ListOf
    {

        return parent::topButtons()->add(
            ActionButton::make('Настройка статусов',
                url: fn($model) => $this->getPageUrl(OrderStatuses::class),
            ),
        );
    }

    /**
     * @param Order $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [
            'status' => [
                'required',
                'integer',
                Rule::enum(OrderStatus::class)
            ],
        ];
    }

    public function validationMessages(): array
    {
        return [
            'status.required' => 'Статус обязательное поле',
            'status.integer' => 'Статус должен быть числом',
            'status.'.Enum::class => 'Недопустимое значение статуса'
        ];
    }
}
