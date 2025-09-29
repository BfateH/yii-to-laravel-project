<?php

namespace App\Modules\SupportChat\Services;

use App\Enums\AlertType;
use App\Enums\Role;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Modules\Alerts\Services\AlertService;
use App\Modules\SupportChat\Contracts\AttachmentRepositoryInterface;
use App\Modules\SupportChat\Contracts\MessageRepositoryInterface;
use App\Modules\SupportChat\Contracts\WebSocketServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class MessageService
{
    public function __construct(
        protected MessageRepositoryInterface    $messageRepository,
        protected AttachmentRepositoryInterface $attachmentRepository,
        protected WebSocketServiceInterface     $webSocketService,
        protected AlertService                  $alertService
    ) {
    }

    public function sendMessage(Ticket $ticket, array $data, User $user): TicketMessage
    {
        $data['ticket_id'] = $ticket->id;
        $data['user_id'] = $user->id;
        $data['is_admin'] = $user->isAdminRole() || $user->isPartnerRole();

        $message = $this->messageRepository->create($data);
        $this->updateTicketLastReadMessage($ticket, $message, $user);
        $this->handleAttachments($message, $data);

        $message->load(['attachments']);
        $this->sendAlerts($ticket, $message, $user);
        $this->webSocketService->broadcastMessage($message);

        return $message;
    }

    public function getTicketMessages(Ticket $ticket): Collection
    {
        return $this->messageRepository->findByTicket($ticket);
    }

    private function updateTicketLastReadMessage(Ticket $ticket, TicketMessage $message, User $user): void
    {
        if ($user->isAdminRole() || $user->isPartnerRole()) {
            $ticket->last_admin_message_read = $message->id;
        } else {
            $ticket->last_user_message_read = $message->id;
        }
        $ticket->save();
    }

    private function handleAttachments(TicketMessage $message, array $data): void
    {
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
                } else {
                    Log::warning('Error uploading attachment: File is not a valid UploadedFile.', [
                        'message_id' => $message->id,
                        'file_type' => gettype($file),
                        'is_instance_of_uploaded_file' => $file instanceof UploadedFile,
                        'is_valid' => $file instanceof UploadedFile && $file->isValid(),
                    ]);
                }
            }
        }
    }

    private function sendAlerts(Ticket $ticket, TicketMessage $message, User $user): void
    {
        if ($user->isDefaultUserRole()) {
            $partner = $user->partner;

            if ($partner) {
                $this->alertService->send(AlertType::TICKET_MESSAGE_CREATED->value, $partner, $message->toArray());
            } else {
                $admins = User::query()->where('role_id', Role::admin->value)->get();
                foreach ($admins as $admin) {
                    $this->alertService->send(AlertType::TICKET_MESSAGE_CREATED->value, $admin, $message->toArray());
                }
            }
        }
    }
}
