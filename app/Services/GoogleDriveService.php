<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GoogleDriveService
{
    private Client $client;
    private Drive $service;
    private array $tokens = [];
    private string $currentState = '';

    public function __construct()
    {
        $this->client = new Client();
        $this->setupClient();
        $this->service = new Drive($this->client);
    }

    private function setupClient(): void
    {
        $this->client->setApplicationName(config('app.name') . ' Backup');
        $this->client->setScopes([
            Drive::DRIVE_FILE,
            Drive::DRIVE_METADATA_READONLY
        ]);

        // Setup OAuth2 credentials
        $this->client->setClientId(config('services.google_drive.client_id'));
        $this->client->setClientSecret(config('services.google_drive.client_secret'));
        $this->client->setRedirectUri(config('services.google_drive.redirect_uri'));

        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');
        $this->client->setApprovalPrompt('force');
    }

    /**
     * Generate authorization URL for OAuth flow - SqlBak style
     */
    public function getAuthorizationUrl(): string
    {
        $this->currentState = bin2hex(random_bytes(16));
        $this->client->setState($this->currentState);

        return $this->client->createAuthUrl();
    }

    /**
     * Get current state for verification
     */
    public function getState(): string
    {
        return $this->currentState;
    }

    /**
     * Exchange authorization code for access tokens
     */
    public function exchangeCodeForTokens(string $code): ?array
    {
        try {
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($code);

            if (isset($accessToken['error'])) {
                Log::error('Google OAuth token exchange error: ' . $accessToken['error']);
                return null;
            }

            $this->tokens = $accessToken;
            $this->client->setAccessToken($accessToken);

            return $accessToken;
        } catch (\Exception $e) {
            Log::error('Failed to exchange code for tokens: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Set tokens for authenticated requests
     */
    public function setTokens(array $tokens): bool
    {
        try {
            $this->tokens = $tokens;

            // Refresh token if needed
            if ($this->client->isAccessTokenExpired()) {
                if (isset($tokens['refresh_token'])) {
                    $newTokens = $this->client->fetchAccessTokenWithRefreshToken($tokens['refresh_token']);
                    if (!isset($newTokens['error'])) {
                        $this->tokens = array_merge($tokens, $newTokens);
                    }
                }
            }

            $this->client->setAccessToken($this->tokens);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to set Google Drive tokens: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get folders from Google Drive - SqlBak style folder picker
     */
    public function getFolders(string $parentId = null): array
    {
        try {
            $query = "mimeType='application/vnd.google-apps.folder'";
            if ($parentId) {
                $query .= " and '{$parentId}' in parents";
            } else {
                $query .= " and 'root' in parents";
            }

            $response = $this->service->files->listFiles([
                'q' => $query,
                'fields' => 'files(id,name,parents,createdTime)',
                'orderBy' => 'name'
            ]);

            $folders = [];
            foreach ($response->getFiles() as $file) {
                $folders[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'parent_id' => $parentId,
                    'created_time' => $file->getCreatedTime()
                ];
            }

            return $folders;
        } catch (\Exception $e) {
            Log::error('Failed to get Google Drive folders: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Authenticate với Google Drive - SqlBak style
     */
    public function authenticate(array $credentials): bool
    {
        try {
            if (isset($credentials['tokens'])) {
                // OAuth2 tokens from SqlBak-style flow
                return $this->setTokens($credentials['tokens']);
            } elseif (isset($credentials['access_token'])) {
                // Direct tokens
                return $this->setTokens($credentials);
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Google Drive authentication failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Upload file lên Google Drive với chunked resumable upload
     */
    public function uploadFile(string $filePath, string $fileName, string $folderId = null): ?string
    {
        try {
            $fileSize = filesize($filePath);

            // Tăng memory limit nhẹ - không cần load toàn bộ file
            $originalMemoryLimit = ini_get('memory_limit');
            ini_set('memory_limit', '256M');

            // Tăng timeout cho large file uploads
            set_time_limit(1800); // 30 minutes for very large files

            Log::info("Starting upload to Google Drive", [
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'file_size_mb' => round($fileSize / 1024 / 1024, 2),
                'original_memory_limit' => $originalMemoryLimit
            ]);

            $fileMetadata = new DriveFile([
                'name' => $fileName,
                'parents' => $folderId ? [$folderId] : null
            ]);

            $mimeType = $this->getMimeType($fileName);

            // Với file lớn hơn 10MB, dùng resumable upload
            if ($fileSize > 10 * 1024 * 1024) {
                $file = $this->resumableUpload($filePath, $fileMetadata, $mimeType, $fileSize);
            } else {
                // File nhỏ: simple upload
                $content = file_get_contents($filePath);
                $file = $this->service->files->create($fileMetadata, [
                    'data' => $content,
                    'mimeType' => $mimeType,
                    'uploadType' => 'multipart',
                    'fields' => 'id,name,size,createdTime'
                ]);
                unset($content);
            }

            // Khôi phục memory limit
            ini_set('memory_limit', $originalMemoryLimit);

            Log::info("File uploaded to Google Drive successfully", [
                'file_id' => $file->getId(),
                'file_name' => $fileName,
                'size_mb' => round($fileSize / 1024 / 1024, 2)
            ]);

            return $file->getId();

        } catch (\Exception $e) {
            // Khôi phục memory limit nếu có lỗi
            if (isset($originalMemoryLimit)) {
                ini_set('memory_limit', $originalMemoryLimit);
            }

            Log::error('Failed to upload file to Google Drive: ' . $e->getMessage(), [
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_size_mb' => isset($fileSize) ? round($fileSize / 1024 / 1024, 2) : 'unknown',
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Resumable upload cho file lớn - upload từng chunk bằng cURL
     * Hỗ trợ file nhiều GB với memory usage thấp và có retry logic
     */
    private function resumableUpload(string $filePath, DriveFile $fileMetadata, string $mimeType, int $fileSize): DriveFile
    {
        // Không giới hạn thời gian chạy cho file rất lớn
        set_time_limit(0);

        Log::info("Starting chunked resumable upload via cURL", [
            'file_size_mb' => round($fileSize / 1024 / 1024, 2),
            'file_size_gb' => round($fileSize / 1024 / 1024 / 1024, 2)
        ]);

        // Bước 1: Tạo resumable upload session
        // Đảm bảo access token còn hợp lệ
        if ($this->client->isAccessTokenExpired()) {
            Log::info("Access token expired, refreshing...");
            $currentTokens = $this->client->getAccessToken();
            if (isset($currentTokens['refresh_token'])) {
                $newTokens = $this->client->fetchAccessTokenWithRefreshToken($currentTokens['refresh_token']);
                if (!isset($newTokens['error'])) {
                    $this->tokens = array_merge($currentTokens, $newTokens);
                    $this->client->setAccessToken($this->tokens);
                    Log::info("Access token refreshed successfully");
                } else {
                    throw new \Exception("Failed to refresh access token: " . ($newTokens['error'] ?? 'unknown error'));
                }
            } else {
                throw new \Exception("Access token expired and no refresh token available");
            }
        }

        $accessToken = $this->client->getAccessToken()['access_token'];

        Log::info("Using access token", [
            'token_length' => strlen($accessToken),
            'token_prefix' => substr($accessToken, 0, 20) . '...',
            'is_expired' => $this->client->isAccessTokenExpired()
        ]);

        $metadata = [
            'name' => $fileMetadata->getName(),
            'mimeType' => $mimeType
        ];

        if ($fileMetadata->getParents()) {
            $metadata['parents'] = $fileMetadata->getParents();
        }

        // Khởi tạo resumable upload session với timeout dài
        $ch = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json; charset=UTF-8',
                'X-Upload-Content-Type: ' . $mimeType,
                'X-Upload-Content-Length: ' . $fileSize
            ],
            CURLOPT_POSTFIELDS => json_encode($metadata),
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 300, // 5 minutes timeout for initialization
            CURLOPT_CONNECTTIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("Failed to initialize upload session: $error");
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        curl_close($ch);

        // Debug: Log response details
        Log::info("Upload session initialization response", [
            'http_code' => $httpCode,
            'has_location_header' => strpos($headers, 'Location:') !== false,
            'headers_sample' => substr($headers, 0, 500),
            'body' => $body
        ]);

        // Kiểm tra HTTP code trước
        if ($httpCode !== 200) {
            throw new \Exception("Failed to initialize upload session. HTTP $httpCode: $body");
        }

        // Lấy upload URL từ Location header
        preg_match('/Location:\s*(.+)/i', $headers, $matches);
        if (!isset($matches[1])) {
            Log::error("Failed to parse Location header", [
                'headers' => $headers,
                'body' => $body
            ]);
            throw new \Exception('Failed to get resumable upload URL from response headers. Check logs for details.');
        }
        $uploadUrl = trim($matches[1]);

        Log::info("Got resumable upload URL");

        // Bước 2: Upload file theo chunks 10MB (lớn hơn = nhanh hơn)
        $chunkSize = 10 * 1024 * 1024; // 10MB chunks cho hiệu suất tốt hơn
        $handle = fopen($filePath, 'rb');
        $offset = 0;
        $uploadStartTime = time();

        while (!feof($handle)) {
            $chunkStartTime = microtime(true);
            $chunk = fread($handle, $chunkSize);
            $chunkLength = strlen($chunk);

            if ($chunkLength === 0) {
                break; // End of file
            }

            $end = min($offset + $chunkLength - 1, $fileSize - 1);

            // Retry logic: thử upload chunk tối đa 3 lần
            $maxRetries = 3;
            $retryCount = 0;
            $uploaded = false;

            while (!$uploaded && $retryCount < $maxRetries) {
                try {
                    // Tạo temp file cho chunk (tránh memory overhead của data://)
                    $tempChunk = tmpfile();
                    fwrite($tempChunk, $chunk);
                    rewind($tempChunk);

                    $ch = curl_init($uploadUrl);
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_PUT => true,
                        CURLOPT_HTTPHEADER => [
                            'Content-Length: ' . $chunkLength,
                            'Content-Range: bytes ' . $offset . '-' . $end . '/' . $fileSize
                        ],
                        CURLOPT_INFILE => $tempChunk,
                        CURLOPT_INFILESIZE => $chunkLength,
                        CURLOPT_TIMEOUT => 600, // 10 minutes per chunk
                        CURLOPT_CONNECTTIMEOUT => 30
                    ]);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch);
                    curl_close($ch);
                    fclose($tempChunk);

                    // 308 = Resume Incomplete, 200/201 = Success
                    if ($httpCode === 308 || $httpCode === 200 || $httpCode === 201) {
                        $uploaded = true;

                        // Log progress
                        $chunkDuration = microtime(true) - $chunkStartTime;
                        $progress = ($offset + $chunkLength) / $fileSize * 100;
                        $elapsed = time() - $uploadStartTime;
                        $speed = ($offset + $chunkLength) / $elapsed / 1024 / 1024; // MB/s
                        $eta = ($fileSize - $offset - $chunkLength) / ($speed * 1024 * 1024);

                        Log::info("Upload chunk completed", [
                            'progress' => round($progress, 1) . '%',
                            'uploaded_mb' => round(($offset + $chunkLength) / 1024 / 1024, 2),
                            'total_mb' => round($fileSize / 1024 / 1024, 2),
                            'speed_mbps' => round($speed, 2),
                            'eta_seconds' => round($eta),
                            'chunk_time' => round($chunkDuration, 2) . 's'
                        ]);

                        // Nếu hoàn thành
                        if ($httpCode === 200 || $httpCode === 201) {
                            fclose($handle);
                            $fileData = json_decode($response, true);

                            if (!$fileData || !isset($fileData['id'])) {
                                throw new \Exception('Invalid response from Google Drive: ' . $response);
                            }

                            $totalTime = time() - $uploadStartTime;
                            Log::info("Upload completed successfully", [
                                'file_id' => $fileData['id'],
                                'total_time' => $totalTime . 's',
                                'average_speed_mbps' => round($fileSize / $totalTime / 1024 / 1024, 2)
                            ]);

                            // Tạo DriveFile object từ response
                            $file = new DriveFile();
                            $file->setId($fileData['id']);
                            $file->setName($fileData['name']);

                            return $file;
                        }
                    } else {
                        throw new \Exception("Upload chunk failed with HTTP $httpCode: $curlError");
                    }

                } catch (\Exception $e) {
                    $retryCount++;
                    if ($retryCount < $maxRetries) {
                        Log::warning("Chunk upload failed, retrying", [
                            'attempt' => $retryCount,
                            'error' => $e->getMessage(),
                            'offset' => $offset
                        ]);
                        sleep(2 * $retryCount); // Exponential backoff
                    } else {
                        fclose($handle);
                        throw new \Exception("Failed to upload chunk after $maxRetries attempts: " . $e->getMessage());
                    }
                }
            }

            $offset += $chunkLength;
        }

        fclose($handle);
        throw new \Exception('Upload completed but no success response received');
    }    /**
     * Tạo thư mục trên Google Drive
     */
    public function createFolder(string $folderName, string $parentFolderId = null): ?string
    {
        try {
            $fileMetadata = new DriveFile([
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => $parentFolderId ? [$parentFolderId] : null
            ]);

            $folder = $this->service->files->create($fileMetadata, [
                'fields' => 'id'
            ]);

            return $folder->getId();
        } catch (\Exception $e) {
            Log::error('Failed to create folder on Google Drive: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Lấy danh sách file trong thư mục
     */
    public function listFiles(string $folderId = null): array
    {
        try {
            $query = $folderId ? "'{$folderId}' in parents" : "";

            $response = $this->service->files->listFiles([
                'q' => $query,
                'fields' => 'files(id,name,size,createdTime,mimeType)',
                'orderBy' => 'createdTime desc'
            ]);

            return $response->getFiles();
        } catch (\Exception $e) {
            Log::error('Failed to list files from Google Drive: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Xóa file trên Google Drive
     */
    public function deleteFile(string $fileId): bool
    {
        try {
            $this->service->files->delete($fileId);
            Log::info("File deleted from Google Drive", ['file_id' => $fileId]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete file from Google Drive: ' . $e->getMessage(), [
                'file_id' => $fileId
            ]);
            return false;
        }
    }

    /**
     * Lấy thông tin file
     */
    public function getFileInfo(string $fileId): ?array
    {
        try {
            $file = $this->service->files->get($fileId, [
                'fields' => 'id,name,size,createdTime,mimeType,webViewLink'
            ]);

            return [
                'id' => $file->getId(),
                'name' => $file->getName(),
                'size' => $file->getSize(),
                'created_time' => $file->getCreatedTime(),
                'mime_type' => $file->getMimeType(),
                'web_view_link' => $file->getWebViewLink()
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get file info from Google Drive: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Dọn dẹp file cũ theo retention policy
     */
    public function cleanupOldFiles(string $folderId, int $retentionDays): int
    {
        try {
            $cutoffDate = now()->subDays($retentionDays)->toISOString();
            $query = "'{$folderId}' in parents and createdTime < '{$cutoffDate}'";

            $response = $this->service->files->listFiles([
                'q' => $query,
                'fields' => 'files(id,name,createdTime)'
            ]);

            $deletedCount = 0;
            foreach ($response->getFiles() as $file) {
                if ($this->deleteFile($file->getId())) {
                    $deletedCount++;
                }
            }

            Log::info("Cleaned up old backup files from Google Drive", [
                'deleted_count' => $deletedCount,
                'retention_days' => $retentionDays
            ]);

            return $deletedCount;
        } catch (\Exception $e) {
            Log::error('Failed to cleanup old files from Google Drive: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Lấy MIME type dựa trên extension
     */
    private function getMimeType(string $fileName): string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        return match($extension) {
            'zip' => 'application/zip',
            'sql' => 'application/sql',
            'txt' => 'text/plain',
            'json' => 'application/json',
            default => 'application/octet-stream'
        };
    }

    /**
     * Test kết nối với Google Drive
     */
    public function testConnection(): bool
    {
        try {
            $this->service->files->listFiles(['pageSize' => 1]);
            return true;
        } catch (\Exception $e) {
            Log::error('Google Drive connection test failed: ' . $e->getMessage());
            return false;
        }
    }
}
