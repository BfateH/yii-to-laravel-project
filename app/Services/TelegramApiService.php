<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZipStream\ZipStream;

class TelegramApiService
{
    private const TELEGRAM_API_BASE_URL = 'https://api.telegram.org/bot';
    private const TELEGRAM_FILE_BASE_URL = 'https://api.telegram.org/file/bot';

    private string $botToken;

    public function __construct()
    {
        $token = config('services.telegram.bot_token');
        if (!$token) {
            Log::error('TelegramApiService: BOT token not configured.');
        }
        $this->botToken = $token;
    }

    private function getBotToken(): ?string
    {
        if (!$this->botToken) {
            Log::error('TelegramApiService: BOT token not configured.');
        }
        return $this->botToken;
    }

    private function getApiUrl(string $method): ?string
    {
        $token = $this->getBotToken();
        if (!$token) {
            return null;
        }
        return self::TELEGRAM_API_BASE_URL . $token . '/' . $method;
    }

    private function getFileUrl(string $filePath): ?string
    {
        $token = $this->getBotToken();
        if (!$token) {
            return null;
        }
        return self::TELEGRAM_FILE_BASE_URL . $token . '/' . $filePath;
    }

    public function setWebhook(string $webhookUrl, ?string $secretToken = null): array
    {
        $apiUrl = $this->getApiUrl('setWebhook');
        if (!$apiUrl) {
            return [
                'success' => false,
                'message' => 'Bot token is not configured.'
            ];
        }

        $params = ['url' => $webhookUrl];
        if ($secretToken) {
            $params['secret_token'] = $secretToken;
        }

        $response = Http::timeout(30)->post($apiUrl, $params);

        if (!$response->successful()) {
            Log::error('TelegramApiService: Failed to set webhook', [
                'status_code' => $response->status(),
                'response_body' => $response->body()
            ]);
            return [
                'success' => false,
                'message' => 'HTTP Error: ' . $response->status() . ' - ' . $response->body()
            ];
        }

        $responseData = $response->json();
        if (!isset($responseData['ok']) || !$responseData['ok']) {
            Log::error('TelegramApiService: Telegram API error setting webhook', [
                'api_response' => $responseData
            ]);
            return [
                'success' => false,
                'message' => 'Telegram API Error: ' . ($responseData['description'] ?? 'Unknown error')
            ];
        }

        Log::info('TelegramApiService: Webhook set successfully', [
            'webhook_url' => $webhookUrl,
            'has_secret_token' => (bool)$secretToken
        ]);

        return [
            'success' => true,
            'message' => 'Webhook set successfully.',
            'result' => $responseData['result'] ?? null
        ];
    }

    public function sendMessage(int|string $chatId, string $text, array $options = []): array
    {
        $apiUrl = $this->getApiUrl('sendMessage');
        if (!$apiUrl) {
            Log::error('TelegramApiService: Cannot send message, bot token not configured.');
            return [
                'success' => false,
                'message' => 'Bot token is not configured.'
            ];
        }

        $defaultOptions = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        $params = array_merge($defaultOptions, $options);

        try {
            $response = Http::timeout(30)
                ->withOptions(['verify' => config('app.env') !== 'local'])
                ->post($apiUrl, $params);

            if (!$response->successful()) {
                Log::error('TelegramApiService: Failed to send Telegram message', [
                    'chat_id' => $chatId,
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                    'params_sent' => $params
                ]);
                return [
                    'success' => false,
                    'message' => 'HTTP Error: ' . $response->status() . ' - ' . $response->body()
                ];
            }

            $responseData = $response->json();
            if (!isset($responseData['ok']) || !$responseData['ok']) {
                Log::error('TelegramApiService: Telegram API error sending message', [
                    'api_response' => $responseData,
                    'params_sent' => $params
                ]);
                return [
                    'success' => false,
                    'message' => 'Telegram API Error: ' . ($responseData['description'] ?? 'Unknown error')
                ];
            }

            Log::debug('TelegramApiService: Message sent successfully', [
                'chat_id' => $chatId,
                'message_id' => $responseData['result']['message_id'] ?? null
            ]);

            return [
                'success' => true,
                'message_id' => $responseData['result']['message_id'] ?? null,
                'result' => $responseData['result'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error('TelegramApiService: Exception during sending message', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
                'params_sent' => $params
            ]);

            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ];
        }
    }

    public function sendDocument(int|string $chatId, string $documentFileId, ?string $caption = '', array $options = []): array
    {
        $apiUrl = $this->getApiUrl('sendDocument');
        if (!$apiUrl) {
            Log::error('TelegramApiService: Cannot send document, bot token not configured.');
            return [
                'success' => false,
                'message' => 'Bot token is not configured.'
            ];
        }

        $defaultOptions = [
            'chat_id' => $chatId,
            'document' => $documentFileId,
            'caption' => $caption,
        ];

        $params = array_merge($defaultOptions, $options);

        try {
            $response = Http::timeout(30)
                ->withOptions(['verify' => config('app.env') !== 'local'])
                ->post($apiUrl, $params);

            if (!$response->successful()) {
                Log::error('TelegramApiService: Failed to send Telegram document', [
                    'chat_id' => $chatId,
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                    'params_sent' => $params
                ]);
                return [
                    'success' => false,
                    'message' => 'HTTP Error: ' . $response->status() . ' - ' . $response->body()
                ];
            }

            $responseData = $response->json();
            if (!isset($responseData['ok']) || !$responseData['ok']) {
                Log::error('TelegramApiService: Telegram API error sending document', [
                    'api_response' => $responseData,
                    'params_sent' => $params
                ]);
                return [
                    'success' => false,
                    'message' => 'Telegram API Error: ' . ($responseData['description'] ?? 'Unknown error')
                ];
            }

            Log::debug('TelegramApiService: Document sent successfully', [
                'chat_id' => $chatId,
                'message_id' => $responseData['result']['message_id'] ?? null,
                'document_id' => $responseData['result']['document']['file_id'] ?? null
            ]);

            return [
                'success' => true,
                'message_id' => $responseData['result']['message_id'] ?? null,
                'result' => $responseData['result'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error('TelegramApiService: Exception during sending document', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
                'params_sent' => $params
            ]);

            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ];
        }
    }

    public function sendPhoto(int|string $chatId, string $photoFileId, ?string $caption = '', array $options = []): array
    {
        $apiUrl = $this->getApiUrl('sendPhoto');
        if (!$apiUrl) {
            Log::error('TelegramApiService: Cannot send photo, bot token not configured.');
            return [
                'success' => false,
                'message' => 'Bot token is not configured.'
            ];
        }

        $defaultOptions = [
            'chat_id' => $chatId,
            'photo' => $photoFileId,
            'caption' => $caption,
        ];

        $params = array_merge($defaultOptions, $options);

        try {
            $response = Http::timeout(30)
                ->withOptions(['verify' => config('app.env') !== 'local'])
                ->post($apiUrl, $params);

            if (!$response->successful()) {
                Log::error('TelegramApiService: Failed to send Telegram photo', [
                    'chat_id' => $chatId,
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                    'params_sent' => $params
                ]);
                return [
                    'success' => false,
                    'message' => 'HTTP Error: ' . $response->status() . ' - ' . $response->body()
                ];
            }

            $responseData = $response->json();
            if (!isset($responseData['ok']) || !$responseData['ok']) {
                Log::error('TelegramApiService: Telegram API error sending photo', [
                    'api_response' => $responseData,
                    'params_sent' => $params
                ]);
                return [
                    'success' => false,
                    'message' => 'Telegram API Error: ' . ($responseData['description'] ?? 'Unknown error')
                ];
            }

            Log::debug('TelegramApiService: Photo sent successfully', [
                'chat_id' => $chatId,
                'message_id' => $responseData['result']['message_id'] ?? null,
                'photo_id' => $responseData['result']['photo'][0]['file_id'] ?? null // Берем ID первой (самой маленькой) версии фото
            ]);

            return [
                'success' => true,
                'message_id' => $responseData['result']['message_id'] ?? null,
                'result' => $responseData['result'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error('TelegramApiService: Exception during sending photo', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
                'params_sent' => $params
            ]);

            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ];
        }
    }

    public function createForumTopic(int|string $chatId, string $topicName): ?array
    {
        $apiUrl = $this->getApiUrl('createForumTopic');
        if (!$apiUrl) {
            Log::error('TelegramApiService: Cannot create forum topic, bot token not configured.');
            return null;
        }

        try {
            $response = Http::timeout(30)
                ->withOptions(['verify' => config('app.env') !== 'local'])
                ->post($apiUrl, [
                    'chat_id' => $chatId,
                    'name' => $topicName,
                ]);

            if (!$response->successful()) {
                Log::error('TelegramApiService: Failed to create forum topic', [
                    'chat_id' => $chatId,
                    'topic_name' => $topicName,
                    'status_code' => $response->status(),
                    'response_body' => $response->body()
                ]);
                return null;
            }

            $responseData = $response->json();

            if (!isset($responseData['ok']) || !$responseData['ok']) {
                Log::error('TelegramApiService: Telegram API error creating forum topic', [
                    'chat_id' => $chatId,
                    'topic_name' => $topicName,
                    'api_response' => $responseData
                ]);
                return null;
            }

            Log::info('TelegramApiService: Forum topic created successfully', [
                'chat_id' => $chatId,
                'topic_name' => $topicName,
                'result' => $responseData['result']
            ]);

            return $responseData['result'];
        } catch (\Exception $e) {
            Log::error('TelegramApiService: Exception while creating forum topic', [
                'chat_id' => $chatId,
                'topic_name' => $topicName,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    public function deleteForumTopic(int|string $chatId, int|string $messageThreadId): bool
    {
        $apiUrl = $this->getApiUrl('deleteForumTopic');
        if (!$apiUrl) {
            Log::error('TelegramApiService: Cannot delete forum topic, bot token not configured.');
            return false;
        }

        try {
            $response = Http::timeout(30)
                ->withOptions(['verify' => config('app.env') !== 'local'])
                ->post($apiUrl, [
                    'chat_id' => $chatId,
                    'message_thread_id' => $messageThreadId,
                ]);

            if (!$response->successful()) {
                Log::error('TelegramApiService: Failed to delete forum topic', [
                    'chat_id' => $chatId,
                    'message_thread_id' => $messageThreadId,
                    'status_code' => $response->status(),
                    'response_body' => $response->body()
                ]);
                return false;
            }

            $responseData = $response->json();

            if (!isset($responseData['ok']) || !$responseData['ok']) {
                Log::error('TelegramApiService: Telegram API error deleting forum topic', [
                    'chat_id' => $chatId,
                    'message_thread_id' => $messageThreadId,
                    'api_response' => $responseData
                ]);
                return false;
            }

            Log::info('TelegramApiService: Forum topic deleted successfully', [
                'chat_id' => $chatId,
                'message_thread_id' => $messageThreadId
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('TelegramApiService: Exception while deleting forum topic', [
                'chat_id' => $chatId,
                'message_thread_id' => $messageThreadId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function getFile(string $fileId): ?array
    {
        $apiUrl = $this->getApiUrl('getFile');
        if (!$apiUrl) {
            Log::error('TelegramApiService: Cannot get file info, bot token not configured.');
            return null;
        }

        $response = Http::timeout(30)
            ->withOptions(['verify' => config('app.env') !== 'local'])
            ->post($apiUrl, ['file_id' => $fileId]);

        if (!$response->successful()) {
            Log::error('TelegramApiService: Failed to get file info from Telegram', [
                'file_id' => $fileId,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);
            return null;
        }

        $data = $response->json();
        if (!isset($data['ok']) || !$data['ok']) {
            Log::error('TelegramApiService: Telegram API error getting file info', [
                'file_id' => $fileId,
                'api_response' => $data
            ]);
            return null;
        }

        return $data['result'] ?? null;
    }

    public function downloadFile(string $filePath): ?string
    {
        if (!$filePath) {
            Log::error('TelegramApiService: Cannot download file, filePath is empty.');
            return null;
        }

        $fileUrl = $this->getFileUrl($filePath);
        if (!$fileUrl) {
            Log::error('TelegramApiService: Cannot construct file URL, bot token not configured.');
            return null;
        }

        $fileContentsResponse = Http::timeout(60)
            ->withOptions(['verify' => config('app.env') !== 'local'])
            ->get($fileUrl);

        if (!$fileContentsResponse->successful()) {
            Log::error('TelegramApiService: Failed to download file contents from Telegram', [
                'file_path' => $filePath,
                'status_code' => $fileContentsResponse->status(),
                'response_body' => $fileContentsResponse->body(),
            ]);
            return null;
        }

        $fileContents = $fileContentsResponse->body();
        $tempFilePath = tempnam(sys_get_temp_dir(), 'tg_dl_');
        if ($tempFilePath === false) {
            Log::error('TelegramApiService: Could not create temporary file in system temp dir');
            return null;
        }

        $bytesWritten = file_put_contents($tempFilePath, $fileContents);
        if ($bytesWritten === false || $bytesWritten === 0) {
            Log::error('TelegramApiService: Could not write file contents to temporary file', ['path' => $tempFilePath]);
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
            return null;
        }

        if (!file_exists($tempFilePath) || !is_file($tempFilePath) || !is_readable($tempFilePath)) {
            Log::error('TelegramApiService: Downloaded file is invalid for usage', [
                'temp_file_path' => $tempFilePath,
                'exists' => file_exists($tempFilePath),
                'is_file' => is_file($tempFilePath),
                'is_readable' => is_readable($tempFilePath),
            ]);
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
            return null;
        }

        return $tempFilePath;
    }

    public function downloadFileAsUploadedFile(string $fileId, string $originalFileName = null): ?UploadedFile
    {
        $fileInfo = $this->getFile($fileId);
        if (!$fileInfo) {
            Log::error('TelegramApiService: Could not get file info for download.', ['file_id' => $fileId]);
            return null;
        }

        $filePath = $fileInfo['file_path'] ?? null;
        if (!$filePath) {
            Log::error('TelegramApiService: File path not found in Telegram response', ['file_id' => $fileId]);
            return null;
        }

        $tempFilePath = $this->downloadFile($filePath);
        if (!$tempFilePath) {
            Log::error('TelegramApiService: Could not download file contents.', ['file_path' => $filePath]);
            return null;
        }

        if (!$originalFileName) {
            $originalFileName = basename($filePath);
        }

        return new UploadedFile(
            $tempFilePath,
            $originalFileName,
            null,
            null,
            true
        );
    }

    public function sendTextAndAttachmentsMessage(int|string $chatId, string $text, array $options = [], Collection|array $attachments = []): array
    {
        $hasAttachments = !empty($attachments);

        if ($hasAttachments) {
            $zipPath = $this->createAttachmentsArchive($attachments);
            if (!$zipPath || !file_exists($zipPath)) {
                Log::error('TelegramApiService: Failed to create ZIP archive for attachments.');
                $result = $this->sendMessage($chatId, $text, $options);
                $result['attachment_error'] = 'Failed to create or access attachment archive. Text message sent.';
                return $result;
            }

            $result = $this->sendDocumentMessage($chatId, $zipPath, $text, $options);

            if (file_exists($zipPath)) {
                unlink($zipPath);
            }

            return $result;
        } else {
            return $this->sendMessage($chatId, $text, $options);
        }
    }

    private function createAttachmentsArchive(Collection|array $attachments): ?string
    {
        $fileName = 'attachments_' . now()->format('Y-m-d_H-i-s') . '_' . uniqid() . '.zip';
        $absolutePath = storage_path('app/private/' . $fileName);

        $outputStream = fopen($absolutePath, 'wb');
        if (!$outputStream) {
            Log::error('TelegramApiService: Cannot create ZIP file in private storage', ['path' => $absolutePath]);
            return null;
        }

        $zip = new ZipStream(outputStream: $outputStream);

        foreach ($attachments as $attachment) {
            if ($attachment instanceof Attachment) {
                $relativeFilePath = $attachment->file_path;
                $originalName = $attachment->original_name ?? basename($relativeFilePath);
            } else {
                $relativeFilePath = $attachment['file_path'] ?? null;
                $originalName = $attachment['original_name'] ?? basename($relativeFilePath);
            }

            if (empty($relativeFilePath)) {
                Log::warning('TelegramApiService: Attachment has an empty file_path, skipping.', ['attachment' => $attachment]);
                continue;
            }

            $fullFilePath = storage_path('app/public/' . ltrim($relativeFilePath, '/'));

            if (!is_file($fullFilePath)) {
                Log::warning('TelegramApiService: Attachment file not found on disk, skipping.', ['path' => $fullFilePath]);
                continue;
            }

            $content = file_get_contents($fullFilePath);
            if ($content !== false) {
                $zip->addFile($originalName, $content);
            } else {
                Log::warning('TelegramApiService: Could not read attachment file content, skipping.', ['path' => $fullFilePath]);
            }
        }

        $zip->finish();
        fclose($outputStream);
        return file_exists($absolutePath) ? $absolutePath : null;
    }

    private function sendDocumentMessage(int|string $chatId, string $zipPath, string $caption, array $options = []): array
    {
        if (!file_exists($zipPath) || !is_file($zipPath) || !is_readable($zipPath)) {
            Log::error('TelegramApiService: sendDocumentMessage called with an invalid file path.', ['zip_path' => $zipPath]);
            return [
                'success' => false,
                'message' => 'Invalid file path for document.'
            ];
        }

        $zipFileName = 'Вложения.zip';
        $apiUrl = $this->getApiUrl('sendDocument');

        if (!$apiUrl) {
            Log::error('TelegramApiService: Cannot send document, bot token not configured.');
            return [
                'success' => false,
                'message' => 'Bot token is not configured.'
            ];
        }

        try {
            $response = Http::timeout(60)
            ->withOptions(['verify' => config('app.env') !== 'local'])
                ->attach('document', file_get_contents($zipPath), $zipFileName)
                ->post($apiUrl, array_merge([
                    'chat_id' => $chatId,
                    'caption' => $caption,
                    'parse_mode' => 'HTML',
                ], $options));

            if (!$response->successful()) {
                Log::error('TelegramApiService: Failed to send document via HTTP', [
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                    'chat_id' => $chatId,
                ]);
                return [
                    'success' => false,
                    'message' => 'HTTP Error: ' . $response->status() . ' - ' . $response->body()
                ];
            }

            $responseData = $response->json();

            if (!isset($responseData['ok']) || !$responseData['ok']) {
                Log::error('TelegramApiService: Telegram API error sending document', [
                    'api_response' => $responseData,
                    'chat_id' => $chatId,
                ]);
                return [
                    'success' => false,
                    'message' => 'Telegram API Error: ' . ($responseData['description'] ?? 'Unknown error')
                ];
            }

            Log::info('TelegramApiService: Document sent successfully', [
                'chat_id' => $chatId,
                'message_id' => $responseData['result']['message_id'] ?? null,
            ]);

            return [
                'success' => true,
                'message_id' => $responseData['result']['message_id'] ?? null,
                'result' => $responseData['result'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error('TelegramApiService: Exception during sending document', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
}
