<?php

namespace App\Modules\SupportChat\Contracts;

use App\Models\Attachment;
use App\Models\TicketMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

interface AttachmentRepositoryInterface
{
    public function create(TicketMessage $message, UploadedFile $file): Attachment;

    public function findById(int $id): ?Attachment;

    public function findByMessage(TicketMessage $message): Collection;

    public function delete(Attachment $attachment): bool;

    public function deleteByMessage(TicketMessage $message): bool;

    public function validateFile(UploadedFile $file): bool;

    public function getAllowedMimeTypes(): array;

    public function getMaxFileSize(): int;
}
