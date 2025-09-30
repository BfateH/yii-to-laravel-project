<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\shops;

use App\Models\Brand;
use App\Models\Country;
use App\Models\Shop;
use App\Models\ShopCategory;
use App\MoonShine\Fields\CKEditor;
use App\MoonShine\Resources\CountryResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Enums\Ability;
use MoonShine\Laravel\Enums\Action;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\BelongsToMany;
use MoonShine\Laravel\MoonShineRequest;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Color;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Badge;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends ModelResource<Shop>
 */
class ShopResource extends ModelResource
{
    protected string $model = Shop::class;
    protected string $title = 'Магазины';
    protected string $column = 'name';
    protected bool $usePagination = true;
    protected int $itemsPerPage = 10;

    public function isCan(Ability $ability): bool
    {
        $user = Auth::user();
        return $user && $user->isAdminRole();
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::MASS_DELETE);
    }

    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Switcher::make('Активен', 'is_active'),
            Text::make('Название', 'name'),

            BelongsTo::make(
                __('Страна'),
                'country',
                formatted: fn(Country $country) => $country->ru_name,
                resource: CountryResource::class,
            )->badge(Color::PURPLE),

            BelongsToMany::make(
                __('Бренды'),
                'brands',
                formatted: fn(Brand $brand) => $brand->name,
                resource: BrandResource::class,
            )->inLine(badge: fn($model, $value) => Badge::make((string)$value, 'info')),

            BelongsToMany::make(
                __('Категории'),
                'categories',
                formatted: fn(ShopCategory $shopCategory) => $shopCategory->name,
                resource: ShopCategoryResource::class,
            )->inLine(badge: fn($model, $value) => Badge::make((string)$value, 'warning')),

        ];
    }

    /**
     * @return FieldContract
     */
    protected function formFields(): iterable
    {
        return [
            Box::make([
                Tabs::make([
                    Tab::make('Основная информация', [
                        ID::make(),
                        Grid::make([
                            Column::make([
                                Text::make('Название', 'name')->placeholder('Введите название')->required()
                            ])->columnSpan(6),

                            Column::make([
                                Text::make('Slug', 'slug')
                                    ->placeholder('Введите slug либо он сгенерируется автоматически из названия')
                            ])->columnSpan(6),

                            Column::make([
                                Switcher::make('Активен?', 'is_active')->default(true),
                                Switcher::make('Нужен VPN?', 'is_with_vpn')->default(false)
                            ])->columnSpan(6),

                            Column::make([
                                Image::make('Картинка', 'logo_preview')
                                    ->allowedExtensions(['jpeg', 'png', 'jpg', 'gif', 'svg'])
                                    ->removable()
                            ])->columnSpan(6),

                            Column::make([
                                Text::make('Ссылка на магазин', 'link_to_the_store')
                                    ->placeholder('Введите ссылку на магазин')
                            ])->columnSpan(12),

                            Column::make([
                                CKEditor::make('Описание', 'description')
                            ])->columnSpan(12),

                            Column::make([
                                Number::make('Индекс популярности', 'popularity_index')
                                    ->placeholder('Введите индекс популярности')
                            ])->columnSpan(4),

                            Column::make([
                                Number::make('Индекс рейтинга', 'rating_index')
                                    ->placeholder('Введите индекс рейтинга')
                            ])->columnSpan(4),

                            Column::make([
                                Number::make('Базовый индекс сортировки', 'sort_index')
                                    ->placeholder('Введите базовый индекс сортировки')
                            ])->columnSpan(4),

                        ]),
                    ])->icon('information-circle'),
                    Tab::make('Связи', [
                        BelongsTo::make(
                            __('Страна'),
                            'country',
                            formatted: fn(Country $country) => $country->ru_name,
                            resource: CountryResource::class,
                        )->searchable()->nullable()->placeholder('Выберите страну')->required(),

                        Grid::make([
                            Column::make([
                                BelongsToMany::make(
                                    __('Бренды'),
                                    'brands',
                                    formatted: fn(Brand $brand) => $brand->name,
                                    resource: BrandResource::class,

                                )->selectMode()->placeholder('Выберите бренды')
                            ])->columnSpan(6),

                            Column::make([
                                BelongsToMany::make(
                                    __('Категории'),
                                    'categories',
                                    formatted: fn(ShopCategory $shopCategory) => $shopCategory->name,
                                    resource: ShopCategoryResource::class,
                                )->selectMode()->placeholder('Выберите категории')
                            ])->columnSpan(6),
                        ])
                    ])->icon('arrow-right'),
                ]),

            ])
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function detailFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Image::make('Картинка', 'logo_preview'),
            Text::make('Название', 'name'),
            Text::make('Slug', 'slug'),
            Switcher::make('Активен', 'is_active'),
            Switcher::make('Нужен VPN', 'is_with_vpn'),
            Text::make('Ссылка на магазин', 'link_to_the_store'),
            Textarea::make('Описание', 'description'),

            BelongsTo::make(
                __('Страна'),
                'country',
                formatted: fn(Country $country) => $country->ru_name,
                resource: CountryResource::class,
            )->badge(Color::PURPLE),

            BelongsToMany::make(
                __('Бренды'),
                'brands',
                formatted: fn(Brand $brand) => $brand->name,
                resource: BrandResource::class,
            )->inLine(badge: fn($model, $value) => Badge::make((string)$value, 'info')),

            BelongsToMany::make(
                __('Категории'),
                'categories',
                formatted: fn(ShopCategory $shopCategory) => $shopCategory->name,
                resource: ShopCategoryResource::class,
            )->inLine(badge: fn($model, $value) => Badge::make((string)$value, 'warning')),

            Number::make('Индекс популярности', 'popularity_index'),
            Number::make('Индекс рейтинга', 'rating_index'),
            Number::make('Базовый индекс сортировки', 'sort_index'),

        ];
    }

    /**
     * @param Shop $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [
            'country_id' => 'required|integer|exists:countries,id',
            'name' => 'required|string',
            'slug' => [
                'nullable',
                'string',
                Rule::unique('shops')->ignoreModel($item)
            ],
            'is_active' => 'boolean',
            'is_with_vpn' => 'boolean',
            'description' => 'nullable|string',
            'link_to_the_store' => 'nullable|string',
            'logo_preview' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,gif,svg',
                'max:2048',
            ],
            'popularity_index' => 'nullable|integer',
            'rating_index' => 'nullable|integer',
            'sort_index' => 'nullable|integer',
        ];
    }

    public function reorder(MoonShineRequest $request): void
    {
        if ($request->str('data')->isNotEmpty()) {
            $request->str('data')->explode(',')->each(
                fn($id, $position) => Shop::query()
                    ->where('id', $id)
                    ->update([
                        'sort_index' => $position + 1,
                    ]),
            );
        }
    }

    protected function beforeCreating(mixed $item): mixed
    {
        if (!request('slug')) {
            request()->merge(['slug' => Str::slug(request('name'), '_')]);
        }

        return $item;
    }

    protected function beforeUpdating(mixed $item): mixed
    {
        if (!request('slug')) {
            request()->merge(['slug' => Str::slug(request('name'), '_')]);
        }

        return $item;
    }
}
