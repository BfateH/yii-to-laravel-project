<?php

namespace App\MoonShine\Resources\users;

use App\Enums\Role;
use App\Models\User;
use App\MoonShine\Traits\HasUserActions;
use App\Services\UserService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Laravel\Enums\Ability;
use MoonShine\Laravel\Enums\Action;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;

#[Icon('users')]
abstract class BaseUserResource extends ModelResource
{
    use HasUserActions;

    protected string $model = User::class;
    protected string $column = 'email';
    protected array $with = ['moonshineUserRole'];
    protected bool $indexButtonsInDropdown = true;
    protected UserService $userService;

    public function __construct(CoreContract $core)
    {
        parent::__construct($core);
        $this->userService = app(UserService::class);
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::VIEW, Action::MASS_DELETE);
    }

    protected function modifyEditButton(ActionButtonContract $button): ActionButtonContract
    {
        return $button->setLabel('Редактировать');
    }

    protected function modifyDeleteButton(ActionButtonContract $button): ActionButtonContract
    {
        return $button->setLabel('Удалить');
    }

    public function isCan(Ability $ability): bool
    {
        $user = Auth::user();
        return $user && ($user->isAdminRole() || $user->isPartnerRole());
    }

    protected function detailFields(): iterable
    {
        return $this->indexFields();
    }

    public function indexButtons(): ListOf
    {
        $indexButtons = new ListOf(ActionButtonContract::class, []);

        $indexButtons = $indexButtons->add(
            ActionButton::make('Полное удаление')
                ->method('forceDelete')
                ->icon('trash')
                ->error()
                ->withConfirm(
                    'Подтверждение полного удаления',
                    'Внимание! Это действие необратимо. Все данные будут полностью удалены.',
                    'Удалить навсегда'
                )
                ->canSee(fn(User $user) => $user->trashed() && $user->id !== 1),

            ActionButton::make('Восстановить')
                ->method('restore')
                ->icon('arrow-path')
                ->success()
                ->canSee(fn(User $user) => $user->trashed()),

            $this->getEditButton(isAsync: $this->isAsync())->canSee(fn(User $user) => !$user->trashed()),
            $this->getDeleteButton(isAsync: $this->isAsync())->canSee(fn(User $user) => !$user->trashed() && $user->id !== 1),

            ActionButton::make('Активировать')
                ->method('activate')
                ->icon('check')
                ->success()
                ->canSee(fn(User $user) => !$user->trashed() && !$user->is_active),

            ActionButton::make('Деактивировать')
                ->method('deactivate')
                ->icon('x-mark')
                ->warning()
                ->canSee(fn(User $user) => !$user->trashed() && $user->is_active && $user->id !== 1),

            ActionButton::make('Заблокировать')
                ->method('ban')
                ->icon('lock-closed')
                ->info()
                ->withConfirm(
                    'Подтверждение блокировки',
                    'Вы уверены, что хотите заблокировать?',
                    'Заблокировать'
                )
                ->canSee(fn(User $user) => !$user->trashed() && !$user->is_banned && $user->id !== 1),

            ActionButton::make('Разблокировать', '#')
                ->method('unban')
                ->icon('lock-open')
                ->success()
                ->canSee(fn(User $user) => !$user->trashed() && $user->is_banned),
        );

        return $indexButtons;
    }

    protected function canDeleteItem(mixed $item): bool|string

    {
        if ($item->id === 1) {
            return __('Нельзя удалять сущность с ID = 1');
        }

        return true;
    }

    public function findItem(bool $orFail = false): mixed
    {
        $item = parent::findItem($orFail);

        if ($item && !Gate::forUser(Auth::user())->allows('view', $item)) {
            if ($orFail) {
                abort(404, 'Сущность не найдена или не разрешена для этого ресурса.');
            }
            return null;
        }

        return $item;
    }

    protected function beforeCreating(mixed $item): mixed
    {
        $currentUser = Auth::user();
        if ($currentUser->isPartnerRole()) {
            $item->partner_id = $currentUser->id;
            $item->role_id = Role::user;
        }

        return $item;
    }

    protected function afterUpdated(mixed $item): mixed
    {
        $changes = $item->getChanges();
        if (isset($changes['is_banned'])) {
            $isBanned = $changes['is_banned'];

            if ($isBanned) {
                $this->userService->ban($item, request()->input('ban_reason'));
            } else {
                $this->userService->unban($item);
            }
        }

        return $item;
    }
}
