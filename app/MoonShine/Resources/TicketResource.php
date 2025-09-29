<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enums\AlertType;
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
use App\MoonShine\Resources\users\PartnerResource;
use App\Services\TelegramApiService;
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
    protected int $itemsPerPage = 5;

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::MASS_DELETE, Action::UPDATE, Action::DELETE);
    }

    protected ?PageType $redirectAfterSave = PageType::DETAIL;

    protected function modifyQueryBuilder(\Illuminate\Contracts\Database\Eloquent\Builder $builder): \Illuminate\Contracts\Database\Eloquent\Builder
    {
        $currentUser = Auth::user();

        if ($currentUser->isPartnerRole()) {
            $builder->where(function ($query) use ($currentUser) {
                $query->where('user_id', $currentUser->id)
                    ->orWhereHas('user', function ($userQuery) use ($currentUser) {
                        $userQuery->where('partner_id', $currentUser->id); // Тикеты пользователей, привязанных к партнёру
                    });
            });
        }

        if ($currentUser->isDefaultUserRole()) {
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
                if ($currentUser->isAdminRole() || $currentUser->isPartnerRole()) {
                    return $ticket->messages()->where('id', '>', $ticket->last_admin_message_read)->count();
                }

                return $ticket->messages()->where('id', '>', $ticket->last_user_message_read)->count();
            })->badge(color: Color::RED),

            BelongsTo::make(
                __('Пользователь'),
                'user',
                formatted: fn(User $user) => $user->email,
                resource: CommonUserResource::class,
            )
                ->valuesQuery(fn(Builder $query) => $query->whereIn('role_id', [Role::partner->value, Role::user->value]))
                ->canSee(fn() => $currentUser->isAdminRole() || $currentUser->isPartnerRole()),

            BelongsTo::make(
                __('Партнер'),
                'user',
                formatted: function (User $user) {
                    $partner = $user->partner;

                    if ($partner) {
                        return $partner->email;
                    }

                    return '';
                },
                resource: CommonUserResource::class,
            )
                ->valuesQuery(fn(Builder $query) => $query->whereIn('role_id', [Role::partner->value, Role::user->value]))
                ->canSee(fn() => $currentUser->isAdminRole())
                ->badge(color: Color::INFO),

            Enum::make('Категория обращения', 'category')
                ->attach(TicketCategory::class)
                ->sortable(),

            Enum::make('Статус', 'status')
                ->attach(TicketStatus::class)
                ->sortable()->badge(fn($status, Enum $field) => TicketStatus::tryFrom($status)?->color()),

            Text::make('Тема обращения', 'subject'),
        ];
    }

    protected function filters(): iterable
    {
        $currentUser = Auth::user();
        $filters = [];

        if ($currentUser->isAdminRole()) {
            $filters[] = BelongsTo::make(
                __('Партнер'),
                'user',
                formatted: function (User $user) {
                    return $user->email . ' - ' . $user->name;
                },
                resource: PartnerResource::class,
            )
                ->valuesQuery(fn(Builder $query) => $query->whereIn('role_id', [Role::partner->value]))
                ->nullable()
                ->asyncSearch()
                ->asyncOnInit()
                ->canSee(fn() => $currentUser->isAdminRole())
                ->badge(color: Color::INFO)
                ->onApply(function (Builder $query, $value, BelongsTo $field) {
                    if ($value) {
                        return $query->whereHas('user', function ($userQuery) use ($value) {
                            $userQuery->where('partner_id', $value);
                        });
                    }
                    return $query;
                });
        }

        return $filters;
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
        $canAccess = false;

        // Собственные тикеты
        if ($currentUser->id === $item->user_id) {
            $canAccess = true;
        }

        // Админу все
        if ($currentUser->isAdminRole()) {
            $canAccess = true;
        }

        // Партнёру ещё тикеты его пользователей
        if ($currentUser->isPartnerRole()) {
            $ticketOwner = $item->user;

            if ($ticketOwner && $ticketOwner->partner_id == $currentUser->id) {
                $canAccess = true;
            }
        }

        if ($item) {
            if (!$canAccess) {
                if ($orFail) {
                    abort(404, 'Сущность не найдена или не разрешена для этого ресурса.');
                }
                return null;
            }
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

        if (!$currentUser->isAdminRole()) {
            $ticket = Ticket::query()->find($item->id);

            $websocketService = app(WebSocketService::class);
            $websocketService->broadcastTicketCreated($ticket);

            $ticketData = [
                'id' => $ticket->id,
                'subject' => $ticket->subject ?? '',
                'ticket_link' => '<a href="' . $this->getDetailPageUrl($item->id) . '">Перейти</a>',
            ];

            $alertService = app(AlertService::class);
            $superGroupChatId = null;

            if ($currentUser->isDefaultUserRole()) {
                $partner = $currentUser->partner;

                if ($partner) {
                    $alertService->send(AlertType::TICKET_CREATED->value, $partner, $ticketData);
                    $superGroupChatId = $partner->telegram_support_chat_id;
                } else {
                    $admins = User::query()->where('role_id', Role::admin->value);
                    foreach ($admins as $admin) {
                        try {
                            $alertService->send(AlertType::TICKET_CREATED->value, $admin, $ticketData);
                        } catch (\Exception $exception) {
                            Log::error('Sent to admin failed: ' . $exception->getMessage());
                        }
                    }
                }
            }

            if ($currentUser->isPartnerRole()) {
                $alertService->send(AlertType::TICKET_CREATED->value, $currentUser, $ticketData);
                $superGroupChatId = $currentUser->telegram_support_chat_id;
            }

            if ($superGroupChatId) {
                try {
                    $telegramApiService = app(TelegramApiService::class);
                    $responseResult = $telegramApiService->createForumTopic($superGroupChatId, 'Тикет #{' . $ticket->id . '}');

                    if (isset($responseResult['message_thread_id']) && $responseResult['message_thread_id']) {
                        $ticket->update([
                            'message_thread_id' => $responseResult['message_thread_id'],
                        ]);

                        $categoryName = TicketCategory::from($ticket->category)->toString();

                        $textToTelegram = "✅ Тикет #{" . $ticket->id . "} создан. \n";
                        $textToTelegram .= "<b>Категория обращения:</b> " . $categoryName . "\n";
                        $textToTelegram .= "<b>Тема:</b> " . $ticket->subject . "\n";
                        $textToTelegram .= "<b>Описание:</b> " . $ticket->description;

                        $result = $telegramApiService->sendMessage(
                            $superGroupChatId,
                            $textToTelegram,
                            ['message_thread_id' => $ticket->message_thread_id]
                        );

                        if (!$result['success']) {
                            Log::warning('Failed to send Telegram notification for ticket_created', [
                                'ticket_id' => $ticket->id,
                                'result' => $result,
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to create forum topic: ' . $e->getMessage());
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

        if (!$canCloseItem) {
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

        $ticketUser = $item->user;
        $telegramApiService = app(TelegramApiService::class);

        try {
            if($item->message_thread_id) {
                if ($ticketUser->isPartnerRole() && $ticketUser->telegram_support_chat_id) {
                    $telegramApiService->deleteForumTopic($ticketUser->telegram_support_chat_id, $item->message_thread_id);
                    $telegramApiService->sendMessage($ticketUser->telegram_support_chat_id, '✅ Тикет #{' . $item->id . '} был закрыт');
                }

                if($ticketUser->isDefaultUserRole()) {
                    $partner = $ticketUser->partner;
                    if ($partner && $partner->telegram_support_chat_id) {
                        $telegramApiService->deleteForumTopic($partner->telegram_support_chat_id, $item->message_thread_id);
                        $telegramApiService->sendMessage($partner->telegram_support_chat_id, '✅ Тикет #{' . $item->id . '} был закрыт');
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to delete forum topic: ' . $e->getMessage());
        }

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

        if ($currentUser->id === $item->user_id) {
            $isCanUpdate = true;
        }

        if ($currentUser->isAdminRole()) {
            $isCanUpdate = true;
        }

        if ($currentUser->isPartnerRole()) {
            $partner = $item->user->partner;

            if ($partner && $partner->id === $currentUser->id) {
                $isCanUpdate = true;
            }
        }

        return $isOpenStatus && $isCanUpdate;
    }
}
