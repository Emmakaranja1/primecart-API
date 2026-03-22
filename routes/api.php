<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
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

// Payment routes (JWT authentication required)
Route::middleware('jwt.auth')->prefix('payment')->group(function () {
    Route::post('/initiate', [PaymentController::class, 'initiate'])->name('payment.initiate');
    Route::post('/verify', [PaymentController::class, 'verify'])->name('payment.verify');
    Route::get('/methods', [PaymentController::class, 'methods'])->name('payment.methods');
    Route::get('/status/{id}', [PaymentController::class, 'status'])->name('payment.status');
    
    // M-PESA specific routes
    Route::post('/mpesa/stk-push', [PaymentController::class, 'mpesaStkPush'])->name('payment.mpesa.stk-push');
    Route::get('/mpesa/status/{payment_id}', [PaymentController::class, 'mpesaStatus'])->name('payment.mpesa.status');
    
    // Flutterwave specific routes
    Route::post('/flutterwave/pay', [PaymentController::class, 'flutterwavePay'])->name('payment.flutterwave.pay');
    Route::get('/flutterwave/verify/{reference}', [PaymentController::class, 'flutterwaveVerify'])->name('payment.flutterwave.verify');
    
    // DPO specific routes
    Route::post('/dpo/create', [PaymentController::class, 'dpoCreate'])->name('payment.dpo.create');
    Route::post('/dpo/verify', [PaymentController::class, 'dpoVerify'])->name('payment.dpo.verify');
    
    // PesaPal specific routes
    Route::post('/pesapal/create', [PaymentController::class, 'pesapalCreate'])->name('payment.pesapal.create');
    Route::get('/pesapal/status/{orderTrackingId}', [PaymentController::class, 'pesapalStatus'])->name('payment.pesapal.status');
});

// Payment webhook routes (no authentication - called by payment providers)
Route::prefix('payment/webhooks')->group(function () {
    Route::post('/mpesa', [PaymentController::class, 'mpesaWebhook'])->name('payment.webhook.mpesa');
    Route::post('/dpo', [PaymentController::class, 'dpoWebhook'])->name('payment.webhook.dpo');
    Route::post('/pesapal', [PaymentController::class, 'pesapalWebhook'])->name('payment.webhook.pesapal');
});

// Standardized webhook URLs for all payment providers
Route::post('/payment/flutterwave/webhook', [PaymentController::class, 'flutterwaveWebhook'])
    ->name('payment.flutterwave.webhook');

Route::post('/payment/mpesa/webhook', [PaymentController::class, 'mpesaWebhook'])
    ->name('payment.mpesa.webhook');

Route::post('/payment/dpo/webhook', [PaymentController::class, 'dpoWebhook'])
    ->name('payment.dpo.webhook');

Route::post('/payment/pesapal/webhook', [PaymentController::class, 'pesapalWebhook'])
    ->name('payment.pesapal.webhook');

// Payment callback routes (no authentication - called by payment providers)
Route::prefix('payment/callbacks')->group(function () {
    Route::match(['get','post'], '/flutterwave', [PaymentController::class, 'flutterwaveCallback'])
        ->name('payment.flutterwave.callback');
});

// Payment status routes (for user redirects after payment)
Route::prefix('payment')->group(function () {
    Route::get('/success', [PaymentController::class, 'paymentSuccess'])->name('payment.success');
    Route::get('/failed', [PaymentController::class, 'paymentFailed'])->name('payment.failed');
});