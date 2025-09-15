<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountDeletionRequested extends Notification
{
    use Queueable;

    public function __construct(protected User $user)
    {
        //
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $url = url('/confirm-delete/' . $this->user->delete_confirmation_token);

        return (new MailMessage)
            ->subject('Подтверждение удаления аккаунта')
            ->line('Вы запросили удаление своего аккаунта.')
            ->action('Подтвердить удаление', $url)
            ->line('Ссылка действительна в течение 24 часов.')
            ->line('Внимание! После подтверждения все ваши данные будут безвозвратно удалены.');
    }
}
