<?php

namespace App\Modules\SupportChat\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'user_id' => $this->user_id,
            'message' => $this->message,
            'is_admin' => $this->is_admin,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'last_user_message_read' => $this->ticket->last_user_message_read,
            'last_admin_message_read' => $this->ticket->last_admin_message_read,

            'user' => $this->whenLoaded('user', fn() => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'is_admin' => $this->is_admin,
            ]),

            'attachments' => $this->whenLoaded('attachments', fn() => AttachmentResource::collection($this->attachments)),
        ];
    }
}
