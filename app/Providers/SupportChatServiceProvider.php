<?php

namespace App\Providers;

use App\Modules\Alerts\Services\AlertService;
use Illuminate\Support\ServiceProvider;

class SupportChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            \App\Modules\SupportChat\Contracts\TicketRepositoryInterface::class,
            \App\Modules\SupportChat\Repositories\TicketRepository::class
        );

        $this->app->bind(
            \App\Modules\SupportChat\Contracts\MessageRepositoryInterface::class,
            \App\Modules\SupportChat\Repositories\MessageRepository::class
        );

        $this->app->bind(
            \App\Modules\SupportChat\Contracts\AttachmentRepositoryInterface::class,
            \App\Modules\SupportChat\Repositories\AttachmentRepository::class
        );

        $this->app->bind(
            \App\Modules\SupportChat\Contracts\WebSocketServiceInterface::class,
            \App\Modules\SupportChat\Services\WebSocketService::class
        );

        $this->app->singleton(
            \App\Modules\SupportChat\Services\MessageService::class,
            function ($app) {
                return new \App\Modules\SupportChat\Services\MessageService(
                    $app->make(\App\Modules\SupportChat\Contracts\MessageRepositoryInterface::class),
                    $app->make(\App\Modules\SupportChat\Contracts\AttachmentRepositoryInterface::class),
                    $app->make(\App\Modules\SupportChat\Contracts\WebSocketServiceInterface::class),
                    $app->make(AlertService::class),
                );
            }
        );
    }

    public function boot()
    {

    }
}
