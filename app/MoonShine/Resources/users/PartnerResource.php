<?php

namespace App\MoonShine\Resources\users;

use App\Enums\Role;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use MoonShine\Laravel\Enums\Ability;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
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

#[Icon('briefcase')]
#[Group('Партнеры')]
#[Order(2)]
class PartnerResource extends BaseUserResource
{
    public function isCan(Ability $ability): bool
    {
        $user = Auth::user();
        return $user && $user->isAdminRole();
    }

    public function getTitle(): string
    {
        return __('Партнеры');
    }

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make(__('Имя'), 'name'),
            Email::make(__('Email'), 'email')->sortable(),
            Switcher::make(__('Активен'), 'is_active')->sortable(),
            Switcher::make(__('Заблокирован'), 'is_banned')->sortable(),
            Date::make(__('Создан'), 'created_at')->format("d.m.Y")->sortable(),
        ];
    }

    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        $filters = request()->query('filter', []);
        $withTrashedValue = $filters['with_trashed'] ?? null;

        if ($withTrashedValue) {
            $builder->onlyTrashed();
        }

        return $builder->where('role_id', Role::partner->value);
    }

    protected function formFields(): iterable
    {
        return [
            Box::make([
                Tabs::make([
                    Tab::make(__('Основная информация'), [
                        ID::make(),

                        Hidden::make('role_id')
                            ->default(Role::partner->value)
                            ->fill(Role::partner->value)
                            ->onAfterApply(fn($item, $value) => Role::partner->value),

                        Flex::make([
                            Text::make(__('Имя'), 'name')->required(),
                            Email::make(__('Email'), 'email')->required(),
                        ]),

                        Image::make(__('Аватар'), 'avatar')
                            ->disk(moonshineConfig()->getDisk())
                            ->dir('moonshine_users')
                            ->allowedExtensions(['jpg', 'png', 'jpeg', 'gif']),

                        Collapse::make(__('Пароль'), [
                            Password::make(__('Пароль'), 'password')
                                ->customAttributes(['autocomplete' => 'new-password'])
                                ->eye(),

                            PasswordRepeat::make(__('Повторите пароль'), 'password_repeat')
                                ->customAttributes(['autocomplete' => 'confirm-password'])
                                ->eye(),
                        ])->icon('lock-closed'),

                    ])->icon('user-circle'),

                    Tab::make(__('Статус и безопасность'), [
                        Switcher::make(__('Активен'), 'is_active')->default(true),
                        Switcher::make(__('Заблокирован'), 'is_banned'),
                        Textarea::make(__('Причина блокировки'), 'ban_reason'),
                    ])->icon('shield-check'),
                ]),
            ]),
        ];
    }

    protected function rules(mixed $item): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignoreModel($item),
            ],
            'role_id' => 'required|in:' . Role::partner->value,
            'is_active' => 'boolean',
            'is_banned' => 'boolean',
            'password' => $item->exists
                ? 'sometimes|nullable|min:6|required_with:password_repeat|same:password_repeat'
                : 'required|min:6|required_with:password_repeat|same:password_repeat',
        ];
    }

    protected function search(): array
    {
        return [
            'email',
            'is_active',
            'is_banned',
        ];
    }

    protected function filters(): iterable
    {
        return [
            Text::make('Email', 'email')->nullable(),
            Select::make('Активен', 'is_active')
                ->options([
                    null => 'Все',
                    0 => 'Не активен',
                    1 => 'Активен',
                ])
                ->default(null),
            Select::make('Заблокирован', 'is_banned')
                ->options([
                    null => 'Все',
                    0 => 'Не заблокирован',
                    1 => 'Заблокирован',
                ])
                ->default(null),
            Switcher::make('Удаленные партнеры', 'with_trashed')
                ->onApply(fn(Builder $builder, $value) => $builder)
                ->default(false),
        ];
    }

    protected function canUpdateItem(mixed $item): bool|string
    {
        $currentUser = Auth::user();

        if (!$currentUser->isAdminRole()) {
            return __('Только администраторы могут редактировать партнеров.');
        }

        return true;
    }

    protected function canForceDeleteItem(mixed $item): bool|string
    {
        $currentUser = Auth::user();
        if (!$currentUser->isAdminRole()) {
            return __('Только администраторы могут удалять партнеров.');
        }

        if ($item->id === 1) {
            return __('Нельзя удалять пользователя с ID = 1');
        }

        return true;
    }

    protected function beforeUpdating(mixed $item): mixed
    {
        if (!Gate::forUser(Auth::user())->allows('updatePartner', $item)) {
            throw new \Exception(__('Недостаточно прав для редактирования.'));
        }

        return $item;
    }

}
