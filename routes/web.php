<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoginController;
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
});
