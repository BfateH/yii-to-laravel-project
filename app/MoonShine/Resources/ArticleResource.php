<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\User;
use App\MoonShine\Resources\users\CommonUserResource;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\EasyMde\Fields\Markdown;
use MoonShine\Laravel\Enums\Ability;
use MoonShine\Laravel\Enums\Action;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\BelongsToMany;
use MoonShine\Laravel\Http\Responses\MoonShineJsonResponse;
use MoonShine\Laravel\MoonShineRequest;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Enums\Color;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\Support\Enums\ToastType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<Article>
 */
class ArticleResource extends ModelResource
{
    protected string $model = Article::class;

    protected string $title = 'Статьи';
    protected bool $usePagination = false;

    public function isCan(Ability $ability): bool
    {
        $user = Auth::user();
        return $user && ($user->isAdminRole() || $user->isPartnerRole());
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::MASS_DELETE);
    }

    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        $currentUser = Auth::user();

        // свои статьи + default
        if ($currentUser->isPartnerRole()) {
            $builder->where('user_id', $currentUser->id)->orWhere('user_id', null);
        }

        return $builder->orderBy('user_id')->orderBy('sort_index');
    }

    protected function indexButtons(): ListOf
    {
        $currentUser = Auth::user();

        parent::indexButtons();
        $buttons = [
            ActionButton::make('Копировать статью')
                ->method('copyArticle')
                ->icon('clipboard-document-check')
                ->info()
                ->withConfirm(
                    'Точно сделать копирование статьи?',
                    '',
                    'Копировать'
                )
                ->canSee(fn(Article $article) => $article->user_id === null),

            $this->getEditButton(
                isAsync: $this->isAsync()
            )->canSee(function (Article $article) use ($currentUser) {
                if ($currentUser->isAdminRole()) {
                    return true;
                }

                if (!$article->user_id && $currentUser->isPartnerRole()) {
                    return false;
                }

                if ($article->user_id && $article->user_id === $currentUser->id && $currentUser->isPartnerRole()) {
                    return true;
                }

                return false;
            }),

            $this->getDeleteButton(
                isAsync: $this->isAsync()
            )->canSee(function (Article $article) use ($currentUser) {
                if ($currentUser->isAdminRole()) {
                    return true;
                }

                if (!$article->user_id && $currentUser->isPartnerRole()) {
                    return false;
                }

                if ($article->user_id && $article->user_id === $currentUser->id && $currentUser->isPartnerRole()) {
                    return true;
                }

                return false;
            }),

            $this->getDetailButton()->canSee(fn() => !$this->isDetailPage()),
        ];

        return new ListOf(ActionButtonContract::class, $buttons);
    }

    protected function detailButtons(): ListOf
    {
        return $this->indexButtons();
    }

    public function findItem(bool $orFail = false): mixed
    {
        $item = parent::findItem($orFail);
        $currentUser = Auth::user();

        if ($item && $currentUser->isPartnerRole()) {

            if ($item->user_id === $currentUser->id) {
                return $item;
            }

            if ($item->user_id === null && $this->isDetailPage()) {
                return $item;
            }

            if ($orFail) {
                abort(404, 'Сущность не найдена или нет доступа.');
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
            Text::make('Заголовок', 'title'),
            Switcher::make('Статус публикации', 'is_published'),
            Text::make('Slug', 'slug'),
            Number::make('Индекс сортировки', 'sort_index'),

            BelongsTo::make(
                __('Категория'),
                'category',
                formatted: fn(ArticleCategory $articleCategory) => $articleCategory->name,
                resource: ArticleCategoryResource::class
            )->badge(Color::PRIMARY),

            BelongsTo::make(
                __('Чья статья'),
                'user',
                formatted: fn(User $user) => $user->email ?? 'Общая',
                resource: CommonUserResource::class
            )->badge(Color::INFO),
        ];
    }

    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function formFields(): iterable
    {
        $currentUser = Auth::user();

        $flexFields = [];

        if ($currentUser->isPartnerRole()) {
            $flexFields[] = BelongsTo::make(
                __('Категория'),
                'category',
                formatted: fn(ArticleCategory $articleCategory) => $articleCategory->name,
                resource: ArticleCategoryResource::class
            )
                ->valuesQuery(function (Builder $query) use ($currentUser) {
                    return $query
                        ->where('user_id', null)
                        ->orWhere('user_id', $currentUser->id);
                })
                ->nullable()
                ->required()
                ->badge(Color::PRIMARY);
        }

        if ($currentUser->isAdminRole()) {
            $flexFields[] = BelongsTo::make(
                __('Категория'),
                'category',
                formatted: function (ArticleCategory $articleCategory) {
                    if ($articleCategory->user_id) {
                        return $articleCategory->name . ' (' . $articleCategory->user->email . ')';
                    } else {
                        return $articleCategory->name . ' (Общая)';
                    }
                },
                resource: ArticleCategoryResource::class
            )
                ->nullable()
                ->required()
                ->badge(Color::PRIMARY);
        }

        $flexFields[] = Number::make('Индекс сортировки', 'sort_index')->required();

        return [
            Box::make([
                ID::make(),
                Text::make('Заголовок', 'title')->required(),
                Switcher::make('Опубликована?', 'is_published'),

                Flex::make($flexFields),

                BelongsToMany::make(
                    'Связанные статьи',
                    'relatedArticles',
                    formatted: fn(Article $article) => $article->title,
                    resource: ArticleRelatedResource::class,
                )
                    ->valuesQuery(function (Builder $query) use ($currentUser) {
                        if ($currentUser->isPartnerRole()) {
                            $query->where(function (Builder $query) use ($currentUser) {
                                $query->where('user_id', null);
                                $query->orWhere('user_id', $currentUser->id);
                            });
                        }

                        return $query
                            ->where('id', '!=', $this->getItemID())
                            ->where('id', '!=', $this->getItem()?->copied_from_article_id);
                    })
                    ->columnLabel('Заголовок')
                    ->nullable()
                    ->selectMode(),

                Markdown::make('Контент', 'content')->toolbar([
                    'bold', 'italic', 'strikethrough', 'code', 'quote', 'horizontal-rule', '|', 'heading-1',
                    'heading-2', 'heading-3', '|', 'table', 'unordered-list', 'ordered-list', '|', 'link', 'image', '|',
                    'guide',
                ]),
            ])
        ];
    }

    public function filters(): array
    {
        return [
            Text::make('Заголовок', 'title')->nullable(),
        ];
    }

    protected function search(): array
    {
        return [
            'title',
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            Text::make('Заголовок', 'title'),
            Switcher::make('Статус публикации', 'is_published'),
            Text::make('Slug', 'slug'),
            Number::make('Индекс сортировки', 'sort_index'),

            BelongsTo::make(
                __('Категория'),
                'category',
                formatted: fn(ArticleCategory $articleCategory) => $articleCategory->name,
                resource: ArticleCategoryResource::class
            )->badge(Color::PRIMARY),

            BelongsTo::make(
                __('Чья статья'),
                'user',
                formatted: fn(User $user) => $user->email ?? 'Общая',
                resource: CommonUserResource::class
            )->badge(Color::INFO),

            BelongsToMany::make(
                'Связанные статьи',
                'relatedArticles',
                formatted: fn(Article $article) => $article->title,
                resource: ArticleRelatedResource::class,
            )->columnLabel('Заголовок'),

            Markdown::make('Контент', 'content')->defaultMode()->toolbar([]),
        ];
    }

    protected function beforeCreating(mixed $item): mixed
    {
        $currentUser = Auth::user();
        $slug = Str::slug(request('title'));
        request()->merge(['user_id' => null]);

        if ($currentUser->isPartnerRole()) {
            request()->merge(['user_id' => $currentUser->id]);
        }

        $slugExists = Article::query()
            ->where('slug', $slug)
            ->where('user_id', request('user_id'))
            ->exists();

        if ($slugExists) {
            throw new \Exception('Slug already exists.');
        }

        $item->user_id = request('user_id');
        $item->slug = $slug;

        return $item;
    }

    protected function beforeUpdating(mixed $item): mixed
    {
        $slug = Str::slug(request('title'));

        $slugExists = Article::query()
            ->where('slug', $slug)
            ->where('user_id', $item->user_id)
            ->where('id', '!=', $item->id)
            ->exists();

        if ($slugExists) {
            throw new \Exception('Slug already exists.');
        }

        $item->slug = $slug;

        return $item;
    }

    protected function afterCreated(mixed $item): mixed
    {
        Log::info('Article created.', [
            'article' => $item,
            'user_id' => Auth::id(),
        ]);

        return $item;
    }

    protected function afterUpdated(mixed $item): mixed
    {
        Log::info('Article updated.', [
            'article' => $item,
            'user_id' => Auth::id(),
        ]);

        return $item;
    }

    /**
     * @param Article $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:article_categories,id',
            'content' => 'nullable|string|max:100000',
            'sort_index' => 'required|numeric|min:0',
            'is_published' => 'boolean',
        ];
    }

    public function copyArticle(MoonShineRequest $request): MoonShineJsonResponse
    {
        $tableEventName = "index-table-" . $request->getResource()->getUriKey();
        $itemId = $request->getItemID();
        $article = Article::query()->find($itemId);

        if (!$article) {
            return MoonShineJsonResponse::make()
                ->toast("Статья не найдена", ToastType::ERROR);
        }

        if ($article && $article->user_id !== null) {
            return MoonShineJsonResponse::make()
                ->toast("Нельзя копировать чужие статьи", ToastType::ERROR);
        }

        $currentUser = Auth::user();

        $isAlreadyCopied = Article::query()
            ->where('user_id', $currentUser->id)
            ->where('copied_from_article_id', $article->id)
            ->exists();

        if ($isAlreadyCopied) {
            return MoonShineJsonResponse::make()
                ->toast("Вы уже копировали себе эту статью", ToastType::ERROR);
        }

        try {
            $replicate = $article->replicate();
            $replicate->user_id = $currentUser->id;
            $replicate->copied_from_article_id = $article->id;
            $replicate->save();
            $message = 'Статья успешно скопирована.';

            Log::info('Article copied successfully.', [
                'article_id' => $article->id,
                'replicate_id' => $replicate->id,
                'user_id' => $currentUser->id
            ]);

            return MoonShineJsonResponse::make()
                ->toast($message, ToastType::SUCCESS)
                ->events([AlpineJs::event(JsEvent::TABLE_UPDATED, $tableEventName)]);
        } catch (\Exception $e) {

            Log::error('Error copy article: ', [
                'article_id' => $article->id,
                'user_id' => $currentUser->id
            ]);

            return MoonShineJsonResponse::make()
                ->toast("Ошибка при копировании статьи", ToastType::ERROR);
        }
    }
}
