<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\shops;

use App\Models\Brand;
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
 * @extends ModelResource<Brand>
 */
class BrandResource extends ModelResource
{
    protected string $model = Brand::class;
    protected string $title = 'Бренды магазинов';
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
            Text::make('Название бренда', 'name')->sortable()
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
                Text::make('Название бренда', 'name')->placeholder('Например: Adidas')
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
     * @param Brand $item
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
}
