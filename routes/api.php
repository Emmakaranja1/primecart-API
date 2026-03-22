<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CartController;
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
    
    // Product management
    Route::get('/products', [ProductController::class, 'adminIndex'])->name('admin.products.list');
    Route::post('/products', [ProductController::class, 'store'])->name('admin.products.create');
    Route::put('/products/{id}', [ProductController::class, 'update'])->name('admin.products.update');
    Route::delete('/products/{id}', [ProductController::class, 'destroy'])->name('admin.products.delete');
});

// User product routes (public access)
Route::get('/products', [ProductController::class, 'index'])->name('products.list');
Route::get('/products/{id}', [ProductController::class, 'show'])->name('products.show');


// User cart routes (JWT authentication required)
Route::middleware('jwt.auth')->prefix('cart')->group(function () {
    Route::post('/add', [CartController::class, 'add'])->name('cart.add');
    Route::get('/', [CartController::class, 'index'])->name('cart.index');
    Route::put('/{id}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/{id}', [CartController::class, 'destroy'])->name('cart.destroy');
});

// User order routes (JWT authentication required)
Route::middleware('jwt.auth')->prefix('orders')->group(function () {
    Route::post('/', [OrderController::class, 'store'])->name('orders.store');
    Route::get('/', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/{id}', [OrderController::class, 'show'])->name('orders.show');
});

// Admin order routes (JWT + Admin role required)
Route::middleware(['jwt.auth', 'admin'])->prefix('admin/orders')->group(function () {
    Route::get('/', [OrderController::class, 'adminIndex'])->name('admin.orders.index');
});