<?php

namespace App\Modules\SupportChat\Services;

use App\Events\SupportChat\MessageSent;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Modules\SupportChat\Contracts\AttachmentRepositoryInterface;
use App\Modules\SupportChat\Contracts\MessageRepositoryInterface;
use App\Modules\SupportChat\Contracts\WebSocketServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class MessageService
{
    public function __construct(
        protected MessageRepositoryInterface $messageRepository,
        protected AttachmentRepositoryInterface $attachmentRepository,
        protected WebSocketServiceInterface $webSocketService
    ) {}

    public function sendMessage(Ticket $ticket, array $data, User $user): TicketMessage
    {
        $data['ticket_id'] = $ticket->id;
        $data['user_id'] = $user->id;
        $data['is_admin'] = $user->isAdminRole() ?? false;

        $message = $this->messageRepository->create($data);

        if($user->isAdminRole()){
            $ticket->last_admin_message_read = $message->id;
        } else {
            $ticket->last_user_message_read = $message->id;
        }

        $ticket->save();


        if (!empty($data['attachments']) && is_array($data['attachments'])) {
            foreach ($data['attachments'] as $file) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    try {
                        $this->attachmentRepository->create($message, $file);
                    } catch (\Exception $e) {
                        Log::error('Error uploading attachment: ' . $e->getMessage(), [
                            'message_id' => $message->id,
                            'file_name' => $file->getClientOriginalName(),
                            'file_size' => $file->getSize(),
                            'file_type' => $file->getMimeType()
                        ]);
                    }
                }
            }
        }

        $this->webSocketService->broadcastMessage($message);
        return $message;
    }

    public function getTicketMessages(Ticket $ticket): Collection
    {
        return $this->messageRepository->findByTicket($ticket);
    }
}
