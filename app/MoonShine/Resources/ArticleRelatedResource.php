<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\ArticleRelated;
use Illuminate\Support\Facades\Auth;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Enums\Ability;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;

/**
 * @extends ModelResource<ArticleRelated>
 */
class ArticleRelatedResource extends ModelResource
{
    protected string $model = ArticleRelated::class;

    protected string $title = 'ArticleRelated';

    public function isCan(Ability $ability): bool
    {
        $user = Auth::user();
        return $user && ($user->isAdminRole() || $user->isPartnerRole());
    }

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
        ];
    }

    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function formFields(): iterable
    {
        return [
            Box::make([
                ID::make(),
            ])
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function detailFields(): iterable
    {
        return [
            ID::make(),
        ];
    }

    /**
     * @param ArticleRelated $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [];
    }
}
