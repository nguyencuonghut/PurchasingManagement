<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\QuotationFileController;
use App\Http\Controllers\SupplierSelectionReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Support\Facades\Route;

/** Login routes */
Route::middleware('guest:web')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('show_login');
    Route::post('/login', [LoginController::class, 'handleLogin'])->name('login');
});
Route::group(['middleware'=>'auth:web'], function() {
    Route::post('/logout', [LoginController::class, 'handleLogout'])->name('logout');
});

Route::group(['middleware'=>'auth:web'], function() {
    //Home routes
    Route::get('/', [HomeController::class, 'home'])->name('home');

    //Profile routes
    Route::get('/profile', [ProfileController::class, 'profile'])->name('profile');

    //User routes
    Route::post('users/bulkDelete', [UserController::class, 'bulkDelete']);
    Route::resource('users', UserController::class);

    //SupplierSelectionReport routes
    Route::resource('/supplier_selection_reports', SupplierSelectionReportController::class);
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
