<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\Channel;
use App\Models\User;
use App\MoonShine\Resources\users\CommonUserResource;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Enums\Ability;
use MoonShine\Laravel\Enums\Action;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Color;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<Alert>
 */
class AlertResource extends ModelResource
{
    protected string $model = Alert::class;
    protected string $title = 'Уведомления';
    protected bool $usePagination = true;
    protected int $itemsPerPage = 25;

    public function isCan(Ability $ability): bool
    {
        $user = Auth::user();
        return $user && ($user->isAdminRole() || $user->isPartnerRole());
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->only(Action::VIEW);
    }

    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        $currentUser = Auth::user();

        if ($currentUser->isPartnerRole()) {
            $builder->where(function ($query) use ($currentUser) {
                $query
                    ->where('user_id', $currentUser->id)
                    ->orWhereHas('user', function ($subQuery) use ($currentUser) {
                        $subQuery->whereHas('partner', function ($subSubQuery) use ($currentUser) {
                            $subSubQuery->where('id', $currentUser->id);
                        });
                    });
            });
        }

        return $builder;
    }

    public function findItem(bool $orFail = false): mixed
    {
        $item = parent::findItem($orFail);
        $currentUser = Auth::user();

        if ($currentUser->isPartnerRole()) {
            $itemUser = $item->user;

            if ($itemUser->id === $currentUser->id) {
                return $item;
            }

            $itemPartner = $itemUser->partner;

            if($itemPartner && $itemPartner->id === $currentUser->id) {
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

            Enum::make('Тип', 'type')
                ->attach(AlertType::class)
                ->sortable()
                ->badge(color: Color::GRAY),

            BelongsTo::make(
                __('Канал'),
                'channel',
                formatted: fn(Channel $channel) => $channel->name,
                resource: ChannelResource::class,
            )->badge(Color::INFO),

            BelongsTo::make(
                __('Пользователь'),
                'user',
                formatted: fn(User $user) => $user->name,
                resource: CommonUserResource::class,
            )->badge(Color::PURPLE),

            Date::make('Отправлено', 'sent_at'),
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
        return [
            ID::make(),
            Enum::make('Тип', 'type')
                ->attach(AlertType::class)
                ->badge(color: Color::GRAY),

            BelongsTo::make(
                __('Канал'),
                'channel',
                formatted: fn(Channel $channel) => $channel->name,
                resource: ChannelResource::class,
            )->badge(Color::INFO),

            BelongsTo::make(
                __('Пользователь'),
                'user',
                formatted: fn(User $user) => $user->name,
                resource: CommonUserResource::class,
            )->badge(Color::PURPLE),

            Date::make('Отправлено', 'sent_at'),
            Text::make('Данные', 'data')->changePreview(fn($value) => is_array($value) ?
                '<pre style="white-space: pre-wrap; background: #2d2d2d; color: #f8f8f2; padding: 10px; border-radius: 4px; max-width: 500px; overflow: auto; border: 1px solid #444;">' .
                htmlspecialchars(stripslashes(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) .
                '</pre>' :
                (string)$value
            ),

            HasMany::make(
                __('Логи'),
                'logs',
                resource: AlertLogResource::class,
            )->searchable(false)
        ];
    }

    /**
     * @param Alert $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [];
    }
}
