<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        return Inertia::render('BackupIndex');
    }

    /**
     * Perform system backup
     */
    public function backup(Request $request)
    {
        // Chỉ admin mới có quyền backup
        $user = $request->user();
        if (optional($user->role)->name !== 'Quản trị') {
            abort(403, 'Bạn không có quyền thực hiện backup.');
        }

        \Log::info($request->all());

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
