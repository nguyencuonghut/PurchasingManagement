<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\SupplierSelectionReportController;
use App\Http\Controllers\UserController;
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

    //User routes
    Route::post('users/bulkDelete', [UserController::class, 'bulkDelete']);
    Route::resource('users', UserController::class);

    //SupplierSelectionReport routes
    Route::resource('/supplier_selection_reports', SupplierSelectionReportController::class);
    Route::put('/supplier_selection_reports/{supplier_selection_report}/send-for-review',
        [SupplierSelectionReportController::class, 'sendForReview'])
        ->name('supplier_selection_reports.sendForReview');
    Route::get('/supplier_selection_reports/{supplierSelectionReport}', [SupplierSelectionReportController::class, 'show'])->name('supplier_selection_reports.show');
    Route::post('/supplier_selection_reports/{supplierSelectionReport}/manager-review', [SupplierSelectionReportController::class, 'managerReview'])->name('supplier_selection_reports.managerReview');
});
