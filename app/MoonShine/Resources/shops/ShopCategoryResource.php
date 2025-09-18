<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\shops;

use App\Models\ShopCategory;
use Illuminate\Support\Facades\Auth;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Enums\Ability;
use MoonShine\Laravel\Enums\Action;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<ShopCategory>
 */
class ShopCategoryResource extends ModelResource
{
    protected string $model = ShopCategory::class;
    protected string $title = 'Категории магазинов';
    protected string $column = 'name';

    public function isCan(Ability $ability): bool
    {
        $user = Auth::user();
        return $user && $user->isAdminRole();
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::VIEW, Action::MASS_DELETE);
    }

    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Название категории', 'name')->sortable(),
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
                Text::make('Название категории', 'name')->placeholder('Например: Одежда'),
            ])
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function detailFields(): iterable
    {
        return [];
    }

    /**
     * @param ShopCategory $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [
            'name' => 'required|string',
        ];
    }

    protected function search(): array
    {
        return [
            'id',
            'name',
        ];
    }
}
