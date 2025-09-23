<?php

namespace App\Modules\SupportChat\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'ticket_message_id' => $this->ticket_message_id,
            'file_path' => $this->file_path,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'url' => $this->url,
            'is_image' => $this->isImage(),
            'file_extension' => $this->getFileExtension(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }

    protected function getFileExtension(): string
    {
        return pathinfo($this->original_name, PATHINFO_EXTENSION);
    }
}
