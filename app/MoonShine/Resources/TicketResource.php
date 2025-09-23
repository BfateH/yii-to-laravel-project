<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enums\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Modules\Alerts\Services\AlertService;
use App\Modules\SupportChat\Enums\TicketCategory;
use App\Modules\SupportChat\Enums\TicketStatus;
use App\Modules\SupportChat\Services\WebSocketService;
use App\MoonShine\Pages\Ticket\TicketDetailPage;
use App\MoonShine\Pages\Ticket\TicketFormPage;
use App\MoonShine\Pages\Ticket\TicketIndexPage;
use App\MoonShine\Resources\users\CommonUserResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Laravel\Enums\Action;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Http\Responses\MoonShineJsonResponse;
use MoonShine\Laravel\MoonShineRequest;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Color;
use MoonShine\Support\Enums\PageType;
use MoonShine\Support\Enums\ToastType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends ModelResource<Ticket, TicketIndexPage, TicketFormPage, TicketDetailPage>
 */
class TicketResource extends ModelResource
{
    protected string $model = Ticket::class;
    protected string $title = 'Поддержка';
    protected bool $usePagination = true;
    protected int $itemsPerPage = 10;

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::MASS_DELETE, Action::UPDATE, Action::DELETE);
    }

    protected ?PageType $redirectAfterSave = PageType::DETAIL;

    protected function modifyQueryBuilder(\Illuminate\Contracts\Database\Eloquent\Builder $builder): \Illuminate\Contracts\Database\Eloquent\Builder
    {
        $currentUser = Auth::user();

        if (!$currentUser->isAdminRole()) {
            $builder->where('user_id', $currentUser->id);
        }

        return $builder;
    }

    protected function modifyDetailButton(ActionButtonContract $button): ActionButtonContract
    {
        $button->setLabel('Чат')->info()->icon('chat-bubble-bottom-center');
        return $button;
    }

    protected function indexFields(): iterable
    {
        $currentUser = Auth::user();

        return [
            ID::make('ID обращения', 'id')->sortable(),
            Text::make('Новые сообщения', formatted: function (Ticket $ticket) use ($currentUser) {
                if($currentUser->isAdminRole()){
                    return $ticket->messages()->where('id', '>', $ticket->last_admin_message_read)->count();
                } else {
                    return $ticket->messages()->where('id', '>', $ticket->last_user_message_read)->count();
                }
            })->badge(color: Color::RED),

            BelongsTo::make(
                __('Пользователь'),
                'user',
                formatted: fn(User $user) => $user->email,
                resource: CommonUserResource::class,
            )
                ->valuesQuery(fn(Builder $query) => $query->whereIn('role_id', [Role::partner->value, Role::user->value]))
                ->canSee(fn() => $currentUser->isAdminRole()),

            Enum::make('Категория обращения', 'category')
                ->attach(TicketCategory::class)
                ->sortable(),

            Enum::make('Статус', 'status')
                ->attach(TicketStatus::class)
                ->sortable()->badge(fn($status, Enum $field) => TicketStatus::tryFrom($status)?->color()),

            Text::make('Тема обращения', 'subject'),
        ];
    }

    protected function formFields(): iterable
    {
        return [
            Flex::make([
                Enum::make('Категория обращения', 'category')
                    ->attach(TicketCategory::class)
                    ->sortable()
                    ->required()
                    ->nullable(),
                Text::make('Тема обращения', 'subject')->required()
            ]),

            Textarea::make('Описание', 'description')->required(),
        ];
    }

    protected function pages(): array
    {
        return [
            TicketIndexPage::class,
            TicketFormPage::class,
            TicketDetailPage::class,
        ];
    }

    public function findItem(bool $orFail = false): mixed
    {
        $item = parent::findItem($orFail);
        $currentUser = Auth::user();

        if (!$currentUser->isAdminRole() && $item && $item->user_id !== $currentUser->id) {
            if ($orFail) {
                abort(404, 'Сущность не найдена или не разрешена для этого ресурса.');
            }
            return null;
        }

        return $item;
    }

    protected function modifyCreateButton(ActionButtonContract $button): ActionButtonContract
    {
        return $button->setLabel('Создать обращение');
    }

    /**
     * @param Ticket $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [
            'category' => [
                'required',
                Rule::enum(TicketCategory::class)
            ],
            'subject' => 'required|string',
            'description' => 'required|string',
        ];
    }

    protected function beforeCreating(mixed $item): mixed
    {
        $currentUser = Auth::user();
        $item->user_id = $currentUser->id;
        return $item;
    }

    protected function afterCreated(mixed $item): mixed
    {
        $currentUser = Auth::user();

        if(!$currentUser->isAdminRole()){
            $ticket = Ticket::query()->find($item->id);

            $websocketService = app(WebSocketService::class);
            $websocketService->broadcastTicketCreated($ticket);

            $alertService = app(AlertService::class);

            $admins = User::query()->where('role_id', Role::admin->value)->get();

            $ticketData = [
                'id' => $ticket->id,
                'subject' => $ticket->subject ?? '',
                'ticket_link' => '<a href="' . $this->getDetailPageUrl($item->id) . '">Перейти</a>',
            ];

            foreach ($admins as $admin) {
                try {
                    $alertService->send('ticket_created', $admin, $ticketData);
                } catch (\Exception $exception) {
                    Log::error('Sent to admin failed: ' . $exception->getMessage());
                }
            }
        }

        return $item;
    }

    public function closeTicket(MoonShineRequest $request): MoonShineJsonResponse
    {
        $itemId = $request->get('id');
        $item = Ticket::query()->find($itemId);
        $canCloseItem = $this->canCloseItem($item);

        if(!$canCloseItem) {
            return MoonShineJsonResponse::make()
                ->toast('Тикет уже закрыт или у вас нет прав.', ToastType::ERROR);
        }

        $oldStatus = $item->status === 'open' ? TicketStatus::OPEN : TicketStatus::IN_PROGRESS;
        $item->update([
            'status' => TicketStatus::CLOSED->value,
            'closed_at' => now(),
        ]);

        $websocketService = app(WebSocketService::class);
        $websocketService->broadcastTicketStatusChanged($item, $oldStatus);

        return MoonShineJsonResponse::make()
            ->toast('Тикет успешно закрыт.', ToastType::SUCCESS)
            ->redirect($this->getDetailPageUrl($item->id));
    }

    protected function canCloseItem(Ticket $item): bool
    {
        $currentUser = Auth::user();

        $isOpenStatus = in_array($item->status, [
            TicketStatus::OPEN->value ?? 'open',
            TicketStatus::IN_PROGRESS->value ?? 'in_progress'
        ]);

        $isCanUpdate = false;

        if($currentUser->id === $item->id) {
            $isCanUpdate = true;
        }

        if($currentUser->isAdminRole()) {
            $isCanUpdate = true;
        }

        return $isOpenStatus && $isCanUpdate;
    }
}
