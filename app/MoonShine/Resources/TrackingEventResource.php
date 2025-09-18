<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\TrackingEvent;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<TrackingEvent>
 */
class TrackingEventResource extends ModelResource
{
    protected string $model = TrackingEvent::class;
    protected string $title = 'TrackingEvents';

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->empty();
    }

    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Date::make('Дата события', 'operation_date')->sortable(),
            Text::make('Тип операции', 'operation_type_name'),
            Text::make('Описание', 'operation_attr_name'),
        ];
    }

    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function formFields(): iterable
    {
        return [];
    }

    /**
     * @return list<FieldContract>
     */
    protected function detailFields(): iterable
    {
        return [];
    }

    /**
     * @param TrackingEvent $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [];
    }
}
