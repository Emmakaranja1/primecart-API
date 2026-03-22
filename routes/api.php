<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
|
|
*/

// Public routes (no authentication required)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/admin/login', [AuthController::class, 'adminLogin'])->name('auth.admin.login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->name('auth.verify-otp');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
});

// Protected routes (JWT authentication required)
Route::middleware('jwt.auth')->prefix('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/profile', [AuthController::class, 'profile'])->name('auth.profile');
});

// Admin routes (JWT + Admin role required)
Route::middleware(['jwt.auth', 'admin'])->prefix('admin')->group(function () {
    // User management
    Route::get('/users', [AdminController::class, 'getUsers'])->name('admin.users.list');
    Route::put('/users/{id}/activate', [AdminController::class, 'activateUser'])->name('admin.users.activate');
    Route::put('/users/{id}/deactivate', [AdminController::class, 'deactivateUser'])->name('admin.users.deactivate');
    
    // Activity logs
    Route::get('/activity-logs', [AdminController::class, 'getActivityLogs'])->name('admin.activity-logs');
});