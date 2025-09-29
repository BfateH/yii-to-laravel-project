<?php

declare(strict_types=1);

namespace App\MoonShine\Pages\Ticket;

use App\Models\User;
use App\Modules\SupportChat\Enums\TicketCategory;
use App\Modules\SupportChat\Enums\TicketStatus;
use App\Modules\SupportChat\Services\WebSocketService;
use App\MoonShine\Components\SupportChatComponent;
use App\MoonShine\Resources\users\CommonUserResource;
use Illuminate\Support\Facades\Auth;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Color;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Divider;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use Throwable;


/**
 * @extends DetailPage<ModelResource>
 */
class TicketDetailPage extends DetailPage
{
    public function getTitle(): string
    {
        return "Тикет #" . $this->getResource()->getItem()?->id;
    }

    protected function prepareBeforeRender(): void
    {
        parent::prepareBeforeRender();

        $currentUser = Auth::user();
        $item = $this->getResource()->getItem();

        if ($currentUser && $item) {
            $needChangeStatus = false;

            if ($currentUser->isAdminRole()) {
                $needChangeStatus = true;
            }

            if ($currentUser->id !== $item->user_id) {
                $needChangeStatus = true;
            }

            if ($item->status !== TicketStatus::OPEN->value) {
                $needChangeStatus = false;
            }

            if ($needChangeStatus) {
                $item->status = TicketStatus::IN_PROGRESS;
                $item->save();
                $item->refresh();
                $webSocketService = app(WebSocketService::class);
                $webSocketService->broadcastTicketStatusChanged($item, TicketStatus::OPEN);
            }
        }
    }

    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        $currentUser = Auth::user();

        return [
            BelongsTo::make(
                'Пользователь',
                'user',
                formatted: fn(User $user) => $user->name,
                resource: CommonUserResource::class
            )->canSee(fn() => $currentUser->isAdminRole() || $currentUser->isPartnerRole()),

            Enum::make('Категория обращения', 'category')
                ->attach(TicketCategory::class)
                ->badge(color: Color::PRIMARY),

            Enum::make('Статус', 'status')
                ->attach(TicketStatus::class)
                ->badge(fn($status, Enum $field) => TicketStatus::tryFrom($status)?->color()),

            Text::make('Тема обращения', 'subject'),
            Textarea::make('Описание', 'description'),
        ];
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function topLayer(): array
    {
        return [
            ...parent::topLayer()
        ];
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function mainLayer(): array
    {
        $item = $this->getResource()->getItem();
        $currentUser = User::query()->find(Auth::id());

        return [
            ...parent::mainLayer(),
            Divider::make(),
            Flex::make(
                [
                    ActionButton::make('Закрыть тикет')
                        ->method('closeTicket', ['id' => $item->id])
                        ->icon('check-circle')
                        ->success()
                        ->withConfirm('Вы уверены, что хотите закрыть тикет?', '')
                        ->canSee(fn() => $item->status === TicketStatus::OPEN->value || $item->status === TicketStatus::IN_PROGRESS->value),
                ]
            )->justifyAlign('end'),
            SupportChatComponent::make($item, $currentUser)
        ];
    }

    /**
     * @return list<ComponentContract>
     * @throws Throwable
     */
    protected function bottomLayer(): array
    {
        return [
            ...parent::bottomLayer(),
        ];
    }
}
