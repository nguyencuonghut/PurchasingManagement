<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\QuotationFileController;
use App\Http\Controllers\SupplierSelectionReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\BackupController;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Support\Facades\Route;

/** Login routes */
Route::middleware('guest:web')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('show_login');
    Route::post('/login', [LoginController::class, 'handleLogin'])->name('login');
    // Hiển thị trang nhập email quên mật khẩu
    Route::get('/forgot-password', function () {
        return Inertia\Inertia::render('Auth/ForgotPassword');
    })->name('password.request');
    // Quên mật khẩu
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
    // Reset mật khẩu
    Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');
    // Route FE nhận token reset
    Route::get('/reset-password/{token}', function (string $token) {
        return Inertia\Inertia::render('Auth/ResetPassword', [
            'token' => $token
        ]);
    })->name('password.reset');
});
Route::group(['middleware'=>'auth:web'], function() {
    Route::post('/logout', [LoginController::class, 'handleLogout'])->name('logout');
});

Route::group(['middleware'=>'auth:web'], function() {
    //Home routes
    Route::get('/', [HomeController::class, 'home'])->name('home');

    //Profile routes
    Route::get('/profile', [ProfileController::class, 'profile'])->name('profile');
    Route::post('/change-password', ChangePasswordController::class)->name('change-password');

    //User routes
    Route::post('users/bulkDelete', [UserController::class, 'bulkDelete']);
    Route::resource('users', UserController::class);

    // Role routes
    Route::resource('roles', RoleController::class)->except(['show', 'create', 'edit']);
    Route::post('roles/bulkDelete', [RoleController::class, 'bulkDelete']);

    // Department routes
    Route::resource('departments', \App\Http\Controllers\DepartmentController::class)->except(['show', 'create', 'edit']);
    Route::post('departments/bulkDelete', [\App\Http\Controllers\DepartmentController::class, 'bulkDelete']);

    // Backup routes
    Route::get('backup', [\App\Http\Controllers\BackupController::class, 'index'])->name('backup.index');
    Route::get('backup/download', [\App\Http\Controllers\BackupController::class, 'backup'])->name('backup.download');
    Route::get('backup/configurations', [\App\Http\Controllers\BackupController::class, 'configurations'])->name('backup.configurations');
    Route::post('backup/configurations', [\App\Http\Controllers\BackupController::class, 'storeConfiguration'])->name('backup.configurations.store');
    Route::put('backup/configurations/{configuration}', [\App\Http\Controllers\BackupController::class, 'updateConfiguration'])->name('backup.configurations.update');
    Route::delete('backup/configurations/{configuration}', [\App\Http\Controllers\BackupController::class, 'deleteConfiguration'])->name('backup.configurations.delete');
    Route::patch('backup/configurations/{configuration}', [\App\Http\Controllers\BackupController::class, 'toggleConfiguration'])->name('backup.configurations.toggle');
    Route::post('backup/configurations/test-google-drive', [\App\Http\Controllers\BackupController::class, 'testGoogleDrive'])->name('backup.configurations.test-google-drive');
    Route::post('backup/configurations/{configuration}/run', [\App\Http\Controllers\BackupController::class, 'runConfiguration'])->name('backup.configurations.run');

    // Google Drive OAuth routes - SqlBak style
    Route::post('/auth/google-drive/connect', [\App\Http\Controllers\GoogleDriveOAuthController::class, 'redirectToGoogle'])->name('google-drive.connect');
    Route::get('/auth/google-drive/callback', [\App\Http\Controllers\GoogleDriveOAuthController::class, 'handleCallback'])->name('google-drive.callback');
    Route::post('/auth/google-drive/exchange-token', [\App\Http\Controllers\GoogleDriveOAuthController::class, 'exchangeToken'])->name('google-drive.exchange-token');
    Route::get('/api/google-drive/status', [\App\Http\Controllers\GoogleDriveOAuthController::class, 'getConnectionStatus'])->name('google-drive.status');
    Route::get('/api/google-drive/folders', [\App\Http\Controllers\GoogleDriveOAuthController::class, 'getFolders'])->name('google-drive.folders');
    Route::post('/api/google-drive/create-folder', [\App\Http\Controllers\GoogleDriveOAuthController::class, 'createBackupFolder'])->name('google-drive.create-folder');
    Route::post('/api/google-drive/select-folder', [\App\Http\Controllers\GoogleDriveOAuthController::class, 'saveFolderSelection'])->name('google-drive.select-folder');
    Route::post('/api/google-drive/disconnect', [\App\Http\Controllers\GoogleDriveOAuthController::class, 'disconnect'])->name('google-drive.disconnect');

    //SupplierSelectionReport routes
    Route::resource('/supplier_selection_reports', SupplierSelectionReportController::class);
    // API lấy danh sách Admin Thu Mua
    Route::get('/api/admin-thu-mua-users', [SupplierSelectionReportController::class, 'getAdminThuMuaUsers'])->name('api.admin_thu_mua_users');
    Route::put('/supplier_selection_reports/{supplier_selection_report}/request-manager-to-approve',
        [SupplierSelectionReportController::class, 'requestManagerToApprove'])
        ->name('supplier_selection_reports.requestManagerToApprove');
        Route::post('/supplier_selection_reports/{supplierSelectionReport}/manager-approve', [SupplierSelectionReportController::class, 'managerApprove'])->name('supplier_selection_reports.managerApprove');
    Route::post('/supplier_selection_reports/{supplierSelectionReport}/auditor-audit', [SupplierSelectionReportController::class, 'auditorAudit'])->name('supplier_selection_reports.auditorAudit');
    Route::post('/supplier_selection_reports/{supplierSelectionReport}/director-approve', [SupplierSelectionReportController::class, 'directorApprove'])->name('supplier_selection_reports.directorApprove');
    Route::put('/supplier_selection_reports/{supplier_selection_report}/request-director-to-approve', [SupplierSelectionReportController::class, 'requestDirectorToApprove'])->name('supplier_selection_reports.requestDirectorToApprove');

    // Quotation files routes
    Route::delete('/quotation_files/{quotationFile}', [QuotationFileController::class, 'destroy'])->name('quotation_files.destroy');
});
