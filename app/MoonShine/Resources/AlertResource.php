<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\Alert;
use App\Models\Channel;
use App\Models\User;
use App\MoonShine\Resources\users\CommonUserResource;
use Illuminate\Support\Facades\Auth;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Enums\Ability;
use MoonShine\Laravel\Enums\Action;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Color;
use MoonShine\Support\Enums\TextWrap;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\Date;
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
        return $user && $user->isAdminRole();
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->only(Action::VIEW);
    }

    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Тип', 'type')->sortable(),

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
            Text::make('Тип', 'type'),

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
                htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) .
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
