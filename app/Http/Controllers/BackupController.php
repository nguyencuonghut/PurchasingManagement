<?php

namespace App\Http\Controllers;

use App\Models\BackupConfiguration;
use App\Models\BackupLog;
use App\Services\AutoBackupService;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use ZipArchive;

class BackupController extends Controller
{
    /**
     * Display backup page
     */
    public function index()
    {
        // Chỉ admin mới có quyền backup
        $user = request()->user();
        if (optional($user->role)->name !== 'Quản trị') {
            abort(403, 'Bạn không có quyền truy cập chức năng này.');
        }

        $configurations = BackupConfiguration::with(['creator', 'logs' => function($query) {
            $query->latest()->limit(5);
        }])->get();

        return Inertia::render('BackupIndex', [
            'configurations' => $configurations,
            'recentLogs' => BackupLog::with(['configuration'])
                ->latest()
                ->limit(10)
                ->get()
        ]);
    }

    /**
     * Display auto backup configurations
     */
    public function configurations()
    {
        // Chỉ admin mới có quyền
        $user = request()->user();
        if (optional($user->role)->name !== 'Quản trị') {
            abort(403, 'Bạn không có quyền truy cập chức năng này.');
        }

        $configurations = BackupConfiguration::with(['creator', 'logs' => function($query) {
            $query->latest()->limit(3);
        }])
        ->get()
        ->map(function ($config) {
            // Add computed attributes for frontend
            $config->formatted_last_run_at = $config->formatted_last_run_at;
            $config->formatted_next_run_at = $config->formatted_next_run_at;
            $config->schedule_description = $config->schedule_description;
            return $config;
        });

        return Inertia::render('BackupConfigurations', [
            'configurations' => $configurations
        ]);
    }

    /**
     * Store new backup configuration
     */
    public function storeConfiguration(Request $request)
    {
        $user = $request->user();
        if (optional($user->role)->name !== 'Quản trị') {
            abort(403, 'Bạn không có quyền thực hiện thao tác này.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'schedule' => 'required|array',
            'backup_options' => 'required|array',
            'notification_emails' => 'required|array|min:1',
            'notification_emails.*' => 'email',
            'retention_days' => 'required|integer|min:1|max:365',
            'google_drive_enabled' => 'boolean',
            'google_drive_config' => 'nullable|array'
        ]);

        // Get Google Drive config with tokens from session if enabled
        $googleDriveConfig = null;
        if ($request->google_drive_enabled) {
            $sessionConfig = Session::get('google_drive_config');
            if ($sessionConfig && isset($sessionConfig['tokens'])) {
                $googleDriveConfig = $sessionConfig;
            } elseif ($request->google_drive_config) {
                // Fallback to request config but log warning
                Log::warning('Google Drive enabled but no tokens in session, using request config');
                $googleDriveConfig = $request->google_drive_config;
            }
        }

        $config = BackupConfiguration::create([
            'name' => $request->name,
            'schedule' => $request->schedule,
            'backup_options' => $request->backup_options,
            'google_drive_enabled' => $request->google_drive_enabled ?? false,
            'google_drive_config' => $googleDriveConfig,
            'notification_emails' => $request->notification_emails,
            'retention_days' => $request->retention_days,
            'created_by' => $user->id
        ]);

        // Tính toán next_run_at
        $config->updateNextRunTime();

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Cấu hình backup đã được tạo thành công!'
        ]);
    }

    /**
     * Test Google Drive connection
     */
    public function testGoogleDrive(Request $request, GoogleDriveService $googleDriveService)
    {
        $user = $request->user();
        if (optional($user->role)->name !== 'Quản trị') {
            abort(403, 'Bạn không có quyền thực hiện thao tác này.');
        }

        try {
            // Get tokens from session instead of request config
            $tokens = Session::get('google_drive_tokens');

            if (!$tokens) {
                return response()->json([
                    'success' => false,
                    'message' => '❌ Chưa có tokens Google Drive. Vui lòng kết nối lại.'
                ]);
            }

            // Set tokens for the service
            $googleDriveService->setTokens($tokens);

            // Test connection by trying to get user info or list a folder
            if ($googleDriveService->testConnection()) {
                return response()->json([
                    'success' => true,
                    'message' => '✅ Kết nối Google Drive thành công! Có thể upload backup.'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '❌ Không thể kết nối với Google Drive. Tokens có thể đã hết hạn.'
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '❌ Lỗi kết nối: ' . $e->getMessage()
            ]);
        }
    }    /**
     * Update backup configuration
     */
    public function updateConfiguration(Request $request, BackupConfiguration $configuration)
    {
        $user = $request->user();
        if (optional($user->role)->name !== 'Quản trị') {
            abort(403, 'Bạn không có quyền thực hiện thao tác này.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'schedule' => 'required|array',
            'backup_options' => 'required|array',
            'notification_emails' => 'required|array|min:1',
            'notification_emails.*' => 'email',
            'retention_days' => 'required|integer|min:1|max:365',
            'google_drive_enabled' => 'boolean',
            'google_drive_config' => 'nullable|array'
        ]);

        // Get Google Drive config with tokens from session if enabled
        $googleDriveConfig = null;
        if ($request->google_drive_enabled) {
            $sessionConfig = Session::get('google_drive_config');
            if ($sessionConfig && isset($sessionConfig['tokens'])) {
                $googleDriveConfig = $sessionConfig;
            } elseif ($request->google_drive_config) {
                // Keep existing config if no new session config
                $googleDriveConfig = $request->google_drive_config;
            } elseif ($configuration->google_drive_config) {
                // Keep existing database config
                $googleDriveConfig = $configuration->google_drive_config;
            }
        }

        $configuration->update([
            'name' => $request->name,
            'schedule' => $request->schedule,
            'backup_options' => $request->backup_options,
            'google_drive_enabled' => $request->google_drive_enabled ?? false,
            'google_drive_config' => $googleDriveConfig,
            'notification_emails' => $request->notification_emails,
            'retention_days' => $request->retention_days,
        ]);

        // Tính toán lại next_run_at
        $configuration->updateNextRunTime();

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Cấu hình backup đã được cập nhật thành công!'
        ]);
    }

    /**
     * Delete backup configuration
     */
    public function deleteConfiguration(Request $request, BackupConfiguration $configuration)
    {
        $user = $request->user();
        if (optional($user->role)->name !== 'Quản trị') {
            abort(403, 'Bạn không có quyền thực hiện thao tác này.');
        }

        $configName = $configuration->name;

        // Delete related logs first
        $configuration->logs()->delete();

        // Delete the configuration
        $configuration->delete();

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => "Đã xóa cấu hình backup '{$configName}' thành công"
        ]);
    }

    /**
     * Toggle backup configuration active status
     */
    public function toggleConfiguration(Request $request, BackupConfiguration $configuration)
    {
        $user = $request->user();
        if (optional($user->role)->name !== 'Quản trị') {
            abort(403, 'Bạn không có quyền thực hiện thao tác này.');
        }

        $configuration->update([
            'is_active' => $request->is_active
        ]);

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => $request->is_active ? 'Đã kích hoạt cấu hình backup' : 'Đã tạm dừng cấu hình backup'
        ]);
    }

    /**
     * Manual run backup configuration
     */
    public function runConfiguration(Request $request, BackupConfiguration $configuration, AutoBackupService $autoBackupService)
    {
        $user = $request->user();
        if (optional($user->role)->name !== 'Quản trị') {
            abort(403, 'Bạn không có quyền thực hiện thao tác này.');
        }

        try {
            $log = $autoBackupService->executeBackup($configuration);

            if ($log->status === 'success') {
                return redirect()->back()->with('flash', [
                    'type' => 'success',
                    'message' => 'Backup đã được thực hiện thành công!'
                ]);
            } else {
                return redirect()->back()->with('flash', [
                    'type' => 'error',
                    'message' => 'Backup thất bại: ' . $log->error_message
                ]);
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ]);
        }
    }    /**
     * Perform system backup
     */
    public function backup(Request $request)
    {
        // Chỉ admin mới có quyền backup
        $user = $request->user();
        if (optional($user->role)->name !== 'Quản trị') {
            abort(403, 'Bạn không có quyền thực hiện backup.');
        }

        $backupPath = null;
        $zipPath = null;

        try {
            $timestamp = now()->format('Y-m-d_H-i-s');
            $backupName = "backup_{$timestamp}";

            // Tạo thư mục tạm cho backup
            $backupPath = storage_path('app/backups/' . $backupName);
            if (!file_exists($backupPath)) {
                mkdir($backupPath, 0755, true);
            }

            Log::info("Starting backup process: {$backupName}");

            // 1. Backup database
            Log::info("Backing up database...");
            $this->backupDatabase($backupPath);

            // 2. Backup .env file
            Log::info("Backing up .env file...");
            $this->backupEnvFile($backupPath);

            // 3. Backup uploaded files (supplier_reports và proposal_files)
            Log::info("Backing up uploaded files...");
            $this->backupUploadedFiles($backupPath);

            // 4. Tạo file zip tổng
            Log::info("Creating zip archive...");
            $zipPath = storage_path('app/backups/' . $backupName . '.zip');
            $this->createZip($backupPath, $zipPath);

            // 5. Dọn dẹp thư mục tạm
            Log::info("Cleaning up temporary files...");
            $this->deleteDirectory($backupPath);

            Log::info("Backup completed successfully: {$backupName}");

            // 6. Trả về file zip để download
            return response()->download($zipPath, $backupName . '.zip')->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error("Backup failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            // Dọn dẹp nếu có lỗi
            if ($backupPath && file_exists($backupPath)) {
                $this->deleteDirectory($backupPath);
            }
            if ($zipPath && file_exists($zipPath)) {
                unlink($zipPath);
            }

            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Có lỗi xảy ra trong quá trình backup: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Backup database to SQL file
     */
    private function backupDatabase($backupPath)
    {
        $databaseName = env('DB_DATABASE');
        $username = env('DB_USERNAME');
        $password = env('DB_PASSWORD');
        $host = env('DB_HOST', '127.0.0.1');
        $port = env('DB_PORT', '3306');

        $sqlFile = $backupPath . '/database.sql';

        // Build mysqldump command
        $passwordOption = !empty($password) ? '--password=' . escapeshellarg($password) : '';
        $command = sprintf(
            'mysqldump --user=%s %s --host=%s --port=%s --single-transaction --routines --triggers %s > %s',
            escapeshellarg($username),
            $passwordOption,
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($databaseName),
            escapeshellarg($sqlFile)
        );

        // Execute mysqldump command
        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new \Exception("Database backup failed. Return code: $returnVar");
        }

        if (!file_exists($sqlFile) || filesize($sqlFile) === 0) {
            throw new \Exception("Database backup file is empty or not created");
        }
    }

    /**
     * Backup .env file
     */
    private function backupEnvFile($backupPath)
    {
        $envPath = base_path('.env');
        $backupEnvPath = $backupPath . '/.env';

        if (file_exists($envPath)) {
            copy($envPath, $backupEnvPath);
        } else {
            throw new \Exception('.env file not found');
        }
    }

    /**
     * Backup uploaded files (supplier_reports và proposal_files)
     */
    private function backupUploadedFiles($backupPath)
    {
        $storagePath = storage_path('app/public');
        $uploadsBackupPath = $backupPath . '/uploads';

        if (!file_exists($uploadsBackupPath)) {
            mkdir($uploadsBackupPath, 0755, true);
        }

        // Backup supplier_reports
        $supplierReportsPath = $storagePath . '/supplier_reports';
        if (is_dir($supplierReportsPath)) {
            $this->copyDirectory($supplierReportsPath, $uploadsBackupPath . '/supplier_reports');
        }

        // Backup proposal_files
        $proposalFilesPath = $storagePath . '/proposal_files';
        if (is_dir($proposalFilesPath)) {
            $this->copyDirectory($proposalFilesPath, $uploadsBackupPath . '/proposal_files');
        }
    }

    /**
     * Copy directory recursively
     */
    private function copyDirectory($src, $dst)
    {
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }

        $files = scandir($src);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;

            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }
    }

    /**
     * Create ZIP archive
     */
    private function createZip($sourceDir, $zipPath)
    {
        $zip = new ZipArchive();
        $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== TRUE) {
            throw new \Exception("Cannot create zip file: $zipPath");
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($sourceDir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
    }

    /**
     * Delete directory recursively
     */
    private function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
