<?php

declare(strict_types=1);

namespace App\MoonShine\Components;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Vite;
use MoonShine\AssetManager\Css;
use MoonShine\AssetManager\Js;
use MoonShine\UI\Components\MoonShineComponent;

/**
 * @method static static make(Ticket $ticket, User $currentUser)
 */
final class SupportChatComponent extends MoonShineComponent
{
    protected string $view = 'admin.components.support-chat-component';

    protected function assets(): array
    {
        return [
            ...parent::assets(),
            Css::make(Vite::asset('resources/css/support-chat.css')),
        ];
    }

    public function __construct(
        protected Ticket $ticket,
        protected User $currentUser
    ) {
        parent::__construct();
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        return [
            'ticket' => $this->ticket,
            'currentUser' => $this->currentUser,
            'isAdmin' => $this->currentUser->isAdminRole() || $this->currentUser->isPartnerRole() ? 1 : 0,
            'ticketId' => $this->ticket->id,
        ];
    }
}
