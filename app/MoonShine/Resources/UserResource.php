<?php

namespace App\MoonShine\Resources;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Models\MoonshineUserRole;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Color;
use MoonShine\UI\Components\Collapse;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Email;
use MoonShine\UI\Fields\Hidden;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Password;
use MoonShine\UI\Fields\PasswordRepeat;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

#[Icon('users')]
#[Group('moonshine::ui.resource.system', 'users', translatable: true)]
#[Order(1)]
class UserResource extends BaseUserResource
{
    public function __construct(CoreContract $core)
    {
        parent::__construct($core);
    }

    public function getTitle(): string
    {
        return __('Пользователи');
    }

    protected function indexFields(): iterable
    {
        $currentUser = Auth::user();

        return [
            ID::make()->sortable(),
            Text::make(__('Имя'), 'name'),
            Email::make(__('Email'), 'email')->sortable(),

            BelongsTo::make(
                __('Роль'),
                'moonshineUserRole',
                formatted: fn(MoonshineUserRole $model) => $model->name,
                resource: MoonShineUserRoleResource::class,
            )->badge(Color::PURPLE)
                ->canSee(fn() => $currentUser->isAdminRole()),

            Text::make(
                __('Партнер'),
                'partner.name'
            )->badge(Color::BLUE)->nullable()
                ->canSee(fn() => $currentUser->isAdminRole()),

            Switcher::make(__('Активен'), 'is_active')->sortable(),
            Switcher::make(__('Заблокирован'), 'is_banned')->sortable(),
            Date::make(__('Создан'), 'created_at')->format("d.m.Y")->sortable(),
        ];
    }

    protected function formFields(): iterable
    {
        $currentUser = Auth::user();
        $isAdmin = $currentUser->isAdminRole();

        $basicFields = [
            ID::make()->sortable(),
            Flex::make([
                Text::make(__('Имя'), 'name')->required(),
                Email::make(__('Email'), 'email')->required(),
            ]),
            Image::make(__('Аватар'), 'avatar')
                ->disk(moonshineConfig()->getDisk())
                ->dir('moonshine_users')
                ->allowedExtensions(['jpg', 'png', 'jpeg', 'gif']),

            BelongsTo::make(
                __('Роль'),
                'moonshineUserRole',
                formatted: fn($model) => $model->name,
                resource: MoonShineUserRoleResource::class,
            )
                ->valuesQuery(fn(Builder $q) => $q->select(['id', 'name'])->where('id', '!=', Role::partner->value))
                ->onAfterApply(function ($item, $value) {
                    if ($value == Role::partner->value) {
                        return $item->role_id;
                    }
                    return $value;
                })
                ->canSee(fn() => $isAdmin),

            BelongsTo::make(
                __('Партнер'),
                'partner',
                resource: PartnerResource::class
            )
                ->asyncSearch('name')
                ->asyncOnInit(whenOpen: false)
                ->valuesQuery(fn(Builder $query) => $query->where('role_id', Role::partner->value))
                ->nullable()
                ->placeholder(__('Выберите партнера'))
                ->canSee(fn() => $isAdmin),

            Hidden::make('partner_id')
                ->default($currentUser->id)
                ->fill($currentUser->id)
                ->canSee(fn() => !$isAdmin),

            Collapse::make(__('Смена пароля'), [
                Password::make(__('Пароль'), 'password')
                    ->customAttributes(['autocomplete' => 'new-password'])
                    ->eye(),
                PasswordRepeat::make(__('Повторите пароль'), 'password_repeat')
                    ->customAttributes(['autocomplete' => 'confirm-password'])
                    ->eye(),
            ])->icon('lock-closed')
        ];

        return [
            Box::make([
                Tabs::make([
                    Tab::make(__('Основная информация'), $basicFields)->icon('user-circle'),

                    Tab::make(__('Статус и безопасность'), [
                        Switcher::make(__('Активен'), 'is_active'),
                        Switcher::make(__('Заблокирован'), 'is_banned'),
                        Textarea::make(__('Причина блокировки'), 'ban_reason'),
                    ])->icon('shield-check'),
                ]),
            ]),
        ];
    }

    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        $filters = request()->query('filter', []);
        $withTrashedValue = $filters['with_trashed'] ?? null;
        $builder = $builder->where('role_id', '!=', Role::partner->value);

        $currentUser = Auth::user();
        if ($currentUser->isPartnerRole()) {
            $builder->where('partner_id', $currentUser->id);
        }

        if ($withTrashedValue) {
            $builder->onlyTrashed();
        }

        return $builder;
    }

    protected function filters(): iterable
    {
        $currentUser = Auth::user();
        $filters = [];

        if ($currentUser->isAdminRole()) {
            $filters[] = BelongsTo::make(
                __('Роль'),
                'moonshineUserRole',
                formatted: fn(MoonshineUserRole $model) => $model->name,
                resource: MoonShineUserRoleResource::class,
            )
                ->valuesQuery(fn(Builder $q) => $q->select(['id', 'name'])->where('id', '!=', Role::partner->value))
                ->nullable()
                ->default(null);

            $filters[] = BelongsTo::make(
                __('Партнер'),
                'partner',
                resource: PartnerResource::class
            )
                ->nullable()
                ->asyncSearch('name')
                ->asyncOnInit()
                ->valuesQuery(fn(Builder $query) => $query->where('role_id', Role::partner->value))
                ->placeholder(__('Выберите партнера'))
                ->default(null);
        }

        $filters = array_merge($filters, [
            Text::make('Email', 'email')->nullable(),

            Select::make('Активен', 'is_active')
                ->options([
                    null => 'Все',
                    0 => 'Не активен',
                    1 => 'Активен',
                ]),

            Select::make('Заблокирован', 'is_banned')
                ->options([
                    null => 'Все',
                    0 => 'Не заблокирован',
                    1 => 'Заблокирован',
                ]),

            Switcher::make('Удаленные пользователи', 'with_trashed')
                ->onApply(fn(Builder $builder, $value) => $builder)
                ->default(false)
        ]);

        return $filters;
    }

    protected function rules($item): array
    {
        $currentUser = Auth::user();
        $isAdmin = $currentUser->isAdminRole();

        $rules = [
            'name' => 'required',
            'email' => [
                'required',
                'bail',
                'email',
                Rule::unique('users')->ignoreModel($item),
            ],
            'avatar' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png,gif'],
            'password' => $item->exists
                ? 'sometimes|nullable|min:6|required_with:password_repeat|same:password_repeat'
                : 'required|min:6|required_with:password_repeat|same:password_repeat',
            'is_active' => 'boolean',
            'is_banned' => 'boolean',
            'ban_reason' => 'nullable|string|max:500',
        ];

        if ($isAdmin) {
            $rules['role_id'] = [
                'required',
                'not_in:' . Role::partner->value,
                'exists:moonshine_user_roles,id'
            ];

            $rules['partner_id'] = [
                'nullable',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    if (is_null($value)) {
                        return;
                    }

                    $partner = User::query()->find($value);
                    if (!$partner || !$partner->isPartnerRole()) {
                        $fail('Выбранный пользователь не является партнером.');
                    }
                },
            ];
        } else {
            $rules['partner_id'] = [
                'required',
                'exists:users,id',
                'in:' . $currentUser->id
            ];
        }

        return $rules;
    }

    protected function search(): array
    {
        $currentUser = Auth::user();

        $searchable = [
            'role_id',
            'email',
            'is_active',
            'is_banned',
        ];

        if ($currentUser->isAdminRole()) {
            $searchable[] = 'partner_id';
        }

        return $searchable;
    }

    protected function canUpdateItem(mixed $item): bool|string
    {
        return Gate::forUser(Auth::user())->allows('update', $item) ?: __('Недостаточно прав для редактирования.');
    }
    protected function canForceDeleteItem(mixed $item): bool|string
    {
        return Gate::forUser(Auth::user())->allows('forceDelete', $item) ?: __('Недостаточно прав для полного удаления.');
    }

    protected function beforeDeleting(mixed $item): mixed
    {
        $checkResult = $this->canDeleteItem($item);

        if ($checkResult !== true) {
            throw new \Exception(is_string($checkResult) ? $checkResult : __('Недостаточно прав для удаления.'));
        }

        return $item;
    }

    protected function beforeUpdating(mixed $item): mixed
    {
        if (!Gate::forUser(Auth::user())->allows('update', $item)) {
            throw new \Exception(__('Недостаточно прав для редактирования.'));
        }

        $newRoleId = request()->input('role_id');
        if ($newRoleId && $newRoleId == Role::partner->value) {
            throw new \Exception('Невозможно назначить роль партнера через этот ресурс.');
        }

        $currentUser = Auth::user();
        if ($currentUser->isPartnerRole()) {
            if (request()->has('role_id')) {
                throw new \Exception('Партнер не может изменять роль пользователя.');
            }

            if (request()->has('partner_id') && request()->input('partner_id') != $currentUser->id) {
                throw new \Exception('Партнер не может изменять привязку к другому партнеру.');
            }
        }

        return $item;
    }
}
