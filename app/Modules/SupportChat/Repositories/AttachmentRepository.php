<?php

namespace App\Modules\SupportChat\Repositories;

use App\Models\Attachment;
use App\Models\TicketMessage;
use App\Modules\SupportChat\Contracts\AttachmentRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class AttachmentRepository implements AttachmentRepositoryInterface
{
    public function create(TicketMessage $message, UploadedFile $file): Attachment
    {
        if (!$this->validateFile($file)) {
            throw new \InvalidArgumentException('Invalid file type or size');
        }

        $path = $file->store('ticket_attachments', 'public');

        return Attachment::query()->create([
            'ticket_message_id' => $message->id,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    public function findById(int $id): ?Attachment
    {
        return Attachment::query()->find($id);
    }

    public function findByMessage(TicketMessage $message): Collection
    {
        return $message->attachments;
    }

    public function delete(Attachment $attachment): bool
    {
        Storage::disk('public')->delete($attachment->file_path);
        return $attachment->delete();
    }

    public function deleteByMessage(TicketMessage $message): bool
    {
        $attachments = $this->findByMessage($message);

        foreach ($attachments as $attachment) {
            $this->delete($attachment);
        }

        return true;
    }

    public function validateFile(UploadedFile $file): bool
    {
        if (!$file->isValid()) {
            return false;
        }

        $allowedTypes = $this->getAllowedMimeTypes();
        $maxSize = $this->getMaxFileSize();

        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();

        if ($fileSize > $maxSize) {
            return false;
        }

        if (in_array($mimeType, $allowedTypes)) {
            return true;
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = $this->getAllowedExtensions();

        return in_array($extension, $allowedExtensions);
    }

    public function getAllowedMimeTypes(): array
    {
        return [
            // Изображения
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',

            // Документы
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
        ];
    }

    public function getAllowedExtensions(): array
    {
        return [
            'jpg', 'jpeg', 'png', 'gif', 'webp',
            'pdf', 'doc', 'docx', 'xls', 'xlsx',
            'txt'
        ];
    }

    public function getMaxFileSize(): int
    {
        return 10 * 1024 * 1024; // 10MB
    }
}
