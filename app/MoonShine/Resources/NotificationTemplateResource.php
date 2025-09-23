<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Models\Channel;
use Illuminate\Database\Eloquent\Model;
use App\Models\NotificationTemplate;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use MoonShine\Laravel\Enums\Ability;
use MoonShine\Laravel\Enums\Action;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Color;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends ModelResource<NotificationTemplate>
 */
class NotificationTemplateResource extends ModelResource
{
    protected string $model = NotificationTemplate::class;
    protected string $title = 'Настройки шаблонов уведомлений';

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
            Text::make('Тип шаблона (key)', 'key'),

            BelongsTo::make(
                __('Канал уведомлений'),
                'channel',
                formatted: fn(Channel $channel) => $channel->name,
                resource: ChannelResource::class,
            )->badge(Color::PURPLE),
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

                BelongsTo::make(
                    __('Канал уведомлений'),
                    'channel',
                    formatted: fn(Channel $channel) => $channel->name,
                    resource: ChannelResource::class,
                )->badge(Color::PURPLE)->nullable()->required(),

                Text::make('Тип шаблона (key)', 'key')->required(),
                Textarea::make('Subject', 'subject')->nullable(),
                Textarea::make('Body', 'body')->required(),

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
            Text::make('Тип шаблона (key)', 'key'),

            BelongsTo::make(
                __('Канал уведомлений'),
                'channel',
                formatted: fn(Channel $channel) => $channel->name,
                resource: ChannelResource::class,
            )->badge(Color::PURPLE),

            Textarea::make('Subject', 'subject')->required(),
            Textarea::make('Body', 'body')->required(),

            Date::make('Создан', 'created_at'),
        ];
    }

    /**
     * @param NotificationTemplate $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [
            'key' => [
                'required',
                'string',
                Rule::unique('notification_templates')->where(function ($query) use ($item) {
                    return $query->where('key', request('key'))
                        ->where('channel_id', request('channel_id'))
                        ->where('id', '!=', $item->id ?? 0);
                })
            ],
            'subject' => 'nullable|string',
            'body' => 'required|string',
            'channel_id' => 'required|numeric|exists:channels,id',
        ];
    }
}
