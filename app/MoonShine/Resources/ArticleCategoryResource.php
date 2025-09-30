<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\ArticleCategory;
use App\Models\User;
use App\MoonShine\Resources\users\CommonUserResource;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Enums\Ability;
use MoonShine\Laravel\Enums\Action;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Color;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<ArticleCategory>
 */
class ArticleCategoryResource extends ModelResource
{
    protected string $model = ArticleCategory::class;
    protected string $title = 'Категории статей';
    protected bool $usePagination = false;

    public function isCan(Ability $ability): bool
    {
        $user = Auth::user();
        return $user && ($user->isAdminRole() || $user->isPartnerRole());
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::MASS_DELETE, Action::VIEW);
    }

    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        $currentUser = Auth::user();

        // свои категории + default
        if ($currentUser->isPartnerRole()) {
            $builder->where('user_id', $currentUser->id)->orWhere('user_id', null);
        }

        return $builder->orderBy('user_id')->orderBy('sort_index');
    }

    protected function indexButtons(): ListOf
    {
        $currentUser = Auth::user();

        $buttons = [
            $this->getEditButton(
                isAsync: $this->isAsync()
            )->canSee(function (ArticleCategory $articleCategory) use ($currentUser) {
                if ($currentUser->isAdminRole()) {
                    return true;
                }

                if (!$articleCategory->user_id && $currentUser->isPartnerRole()) {
                    return false;
                }

                if ($articleCategory->user_id && $articleCategory->user_id === $currentUser->id && $currentUser->isPartnerRole()) {
                    return true;
                }

                return false;
            }),

            $this->getDeleteButton(
                isAsync: $this->isAsync()
            )->canSee(function (ArticleCategory $articleCategory) use ($currentUser) {
                if ($currentUser->isAdminRole()) {
                    return true;
                }

                if (!$articleCategory->user_id && $currentUser->isPartnerRole()) {
                    return false;
                }

                if ($articleCategory->user_id && $articleCategory->user_id === $currentUser->id && $currentUser->isPartnerRole()) {
                    return true;
                }

                return false;
            }),
        ];

        return new ListOf(ActionButtonContract::class, $buttons);
    }

    public function findItem(bool $orFail = false): mixed
    {
        $item = parent::findItem($orFail);
        $currentUser = Auth::user();

        if ($item && $currentUser->isPartnerRole()) {

            if ($item->user_id === $currentUser->id) {
                return $item;
            }

            if ($orFail) {
                abort(404, 'Сущность не найдена или не разрешена для этого ресурса.');
            }

            return null;
        }

        return $item;
    }

    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Название', 'name'),

            BelongsTo::make(
                __('Чья категория'),
                'user',
                formatted: fn(User $user) => $user->email ?? 'Общая',
                resource: CommonUserResource::class
            )->badge(Color::INFO)
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
                Text::make('Название', 'name'),
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

    protected function beforeCreating(mixed $item): mixed
    {
        $currentUser = Auth::user();
        $slug = Str::slug(request('name'));
        request()->merge(['user_id' => null]);

        if ($currentUser->isPartnerRole()) {
            request()->merge(['user_id' => $currentUser->id]);
        }

        $slugExists = ArticleCategory::query()
            ->where('slug', $slug)
            ->where('user_id', request('user_id'))
            ->exists();

        if ($slugExists) {
            throw new \Exception('Category slug already exists.');
        }

        $item->user_id = request('user_id');
        $item->slug = $slug;

        return $item;
    }

    protected function beforeUpdating(mixed $item): mixed
    {
        $slug = Str::slug(request('name'));

        $slugExists = ArticleCategory::query()
            ->where('slug', $slug)
            ->where('user_id', $item->user_id)
            ->where('id', '!=', $item->id)
            ->exists();

        if ($slugExists) {
            throw new \Exception('Category slug already exists.');
        }

        $item->slug = $slug;

        return $item;
    }

    /**
     * @param ArticleCategory $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [
            'name' => 'required|string|max:255',
        ];
    }
}
