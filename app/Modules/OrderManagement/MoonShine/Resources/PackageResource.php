<?php

declare(strict_types=1);

namespace App\Modules\OrderManagement\MoonShine\Resources;

use App\Modules\OrderManagement\Enums\PackageStatus;
use App\Modules\OrderManagement\Models\Package;
use App\Modules\OrderManagement\MoonShine\Pages\Orders\OrderStatuses;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;

/**
 * @extends ModelResource<Package>
 */
class PackageResource extends ModelResource
{
    protected string $model = Package::class;

    protected string $title = 'Посылки';

    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Enum::make('Статус', 'status')->attach(PackageStatus::class)
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
                Enum::make('Статус', 'status')->attach(PackageStatus::class)
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
            Enum::make('Статус', 'status')->attach(PackageStatus::class)
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
     * @param Package $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [];
    }
}
