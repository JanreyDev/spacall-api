<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\PaymongoWebhookController;

// ─── Broadcasting Auth (Reverb / WebSocket channel authorization) ───────────
// Must be in the API (stateless) middleware group so Sanctum reads Bearer tokens.
// Mobile apps cannot send web session cookies, so this cannot be on the web group.
Route::post('/broadcasting/auth', function (Request $request) {
    return Broadcast::auth($request);
})->middleware('auth:sanctum');
// ────────────────────────────────────────────────────────────────────────────

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public Auth
Route::prefix('auth')->group(function () {
    Route::post('/entry', [AuthController::class, 'loginEntry']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/register-profile', [AuthController::class, 'registerProfile']);
    Route::post('/login-pin', [AuthController::class, 'loginPin'])->name('login');
    Route::post('/forgot-pin', [AuthController::class, 'forgotPin']);
    Route::post('/reset-pin', [AuthController::class, 'resetPin']);
    Route::post('/upload-photo', [AuthController::class, 'uploadPhoto'])->middleware('auth:sanctum');
    Route::post('/update-profile', [AuthController::class, 'updateProfile'])->middleware('auth:sanctum');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::delete('/delete-account', [AuthController::class, 'deleteAccount'])->middleware('auth:sanctum');
});

// Admin Auth (New)
Route::post('/admin/login', [\App\Http\Controllers\Api\AdminAuthController::class, 'login']);

// Admin Protected Routes
Route::middleware(['auth:sanctum', \App\Http\Middleware\AdminMiddleware::class])->prefix('admin')->group(function () {
    Route::post('/logout', [\App\Http\Controllers\Api\AdminAuthController::class, 'logout']);
    Route::get('/therapists', [\App\Http\Controllers\Api\AdminTherapistController::class, 'index']);
    Route::get('/clients', [\App\Http\Controllers\Api\AdminClientController::class, 'index']);
    Route::get('/clients/{id}', [\App\Http\Controllers\Api\AdminClientController::class, 'show']);
    Route::get('/bookings', [\App\Http\Controllers\Api\AdminBookingController::class, 'index']);
    Route::get('/reviews', [\App\Http\Controllers\Api\AdminReviewController::class, 'index']);
    Route::get('/messages', [\App\Http\Controllers\Api\AdminMessageController::class, 'index']);
    Route::get('/messages/{bookingId}', [\App\Http\Controllers\Api\AdminMessageController::class, 'show']);
    Route::get('/dashboard-stats', [\App\Http\Controllers\Api\AdminDashboardController::class, 'index']);
    Route::get('/reports', [\App\Http\Controllers\Api\AdminReportController::class, 'index']);
    Route::get('/therapists/{id}', [\App\Http\Controllers\Api\AdminTherapistController::class, 'show']);
    Route::post('/therapists/{id}/approve-vip', [\App\Http\Controllers\Api\AdminTherapistController::class, 'approveVip']);
    Route::post('/therapists/{id}/reject-vip', [\App\Http\Controllers\Api\AdminTherapistController::class, 'rejectVip']);

    // Services Management
    Route::get('/services/categories', [\App\Http\Controllers\Api\AdminServiceController::class, 'categories']);
    Route::post('/services/categories', [\App\Http\Controllers\Api\AdminServiceController::class, 'storeCategory']);
    Route::post('/services/upload-image', [\App\Http\Controllers\Api\AdminServiceController::class, 'uploadImage']);
    Route::apiResource('services', \App\Http\Controllers\Api\AdminServiceController::class);

    // Tier Management
    Route::get('/tiers', [\App\Http\Controllers\Api\Admin\TierController::class, 'index']);
    Route::patch('/tiers/{id}', [\App\Http\Controllers\Api\Admin\TierController::class, 'update']);
    Route::get('/tiers/{id}/members', [\App\Http\Controllers\Api\Admin\TierController::class, 'members']);
});

// Public Services (no auth required)
Route::get('/services', [\App\Http\Controllers\Api\ServiceController::class, 'index']);
Route::get('/services/{slug}', [\App\Http\Controllers\Api\ServiceController::class, 'show']);

// Public Tiers (authenticated)
Route::get('/tiers', [\App\Http\Controllers\Api\Admin\TierController::class, 'index']);

// Protected Routes (Users/Clients/Therapists)
Route::middleware('auth:sanctum')->group(function () {

    // Therapists
    Route::get('/therapists', [\App\Http\Controllers\Api\TherapistController::class, 'index']);
    Route::get('/therapists/{uuid}', [\App\Http\Controllers\Api\TherapistController::class, 'show']);
    Route::get('/therapist/profile', [\App\Http\Controllers\Api\TherapistController::class, 'profile']);
    Route::post('/therapist/location', [\App\Http\Controllers\Api\TherapistController::class, 'updateLocation']);
    Route::post('/therapist/store-profile', [\App\Http\Controllers\Api\TherapistController::class, 'updateStoreProfile']);
    Route::get('/therapist/active-requests', [\App\Http\Controllers\Api\TherapistController::class, 'activeRequests']);
    Route::get('/therapist/nearby-bookings', [\App\Http\Controllers\Api\TherapistController::class, 'nearbyBookings']);
    Route::get('/therapist/dashboard-stats', [\App\Http\Controllers\Api\TherapistController::class, 'dashboardStats']);
    Route::post('/therapist/apply-vip', [\App\Http\Controllers\Api\TherapistController::class, 'applyVip']);

    // Clients
    Route::post('/client/subscribe-vip', [\App\Http\Controllers\Api\ClientController::class, 'subscribeVip']);
    Route::post('/client/verify-pin', [\App\Http\Controllers\Api\ClientController::class, 'verifyPin']);


    // Wallet
    Route::post('/wallet/deposit', [\App\Http\Controllers\Api\WalletController::class, 'deposit']);
    Route::post('/wallet/withdraw', [\App\Http\Controllers\Api\WalletController::class, 'withdraw']);
    Route::get('/wallet/transactions', [\App\Http\Controllers\Api\WalletController::class, 'transactions']);
    Route::post('/wallet/verify-pending', [\App\Http\Controllers\Api\WalletController::class, 'verifyPending']);

    // Bookings
    Route::get('/bookings', [\App\Http\Controllers\Api\BookingController::class, 'index']);
    Route::get('/bookings/available-therapists', [\App\Http\Controllers\Api\BookingController::class, 'availableTherapists']);
    Route::post('/bookings', [\App\Http\Controllers\Api\BookingController::class, 'store']);
    Route::get('/bookings/{id}/track', [\App\Http\Controllers\Api\BookingController::class, 'track']);
    Route::patch('/bookings/{id}/status', [\App\Http\Controllers\Api\BookingController::class, 'updateStatus']);
    Route::get('/bookings/{id}/messages', [BookingController::class, 'getMessages']);
    Route::post('/bookings/{id}/messages', [BookingController::class, 'sendMessage']);
    Route::post('/bookings/{id}/extend', [BookingController::class, 'extend']);
    Route::post('/bookings/{id}/verify-completion', [BookingController::class, 'verifyCompletion']);

    // Stores
    Route::get('/stores', [\App\Http\Controllers\Api\StoreController::class, 'index']);
    Route::get('/stores/{id}', [\App\Http\Controllers\Api\StoreController::class, 'show']);

    // Store Staff Management
    Route::get('/store/staff', [\App\Http\Controllers\Api\StoreStaffController::class, 'index']);
    Route::post('/store/staff', [\App\Http\Controllers\Api\StoreStaffController::class, 'store']);
    Route::get('/store/staff/{id}', [\App\Http\Controllers\Api\StoreStaffController::class, 'show']);
    Route::post('/store/staff/{id}', [\App\Http\Controllers\Api\StoreStaffController::class, 'update']);
    Route::patch('/store/staff/{id}/toggle', [\App\Http\Controllers\Api\StoreStaffController::class, 'toggleStatus']);
    Route::delete('/store/staff/{id}', [\App\Http\Controllers\Api\StoreStaffController::class, 'destroy']);

    // Reviews
    Route::post('/bookings/{id}/reviews', [\App\Http\Controllers\Api\ReviewController::class, 'store']);

    // Support Chat
    Route::prefix('support')->group(function () {
        Route::get('/sessions', [\App\Http\Controllers\Api\SupportChatController::class, 'index']);
        Route::get('/session', [\App\Http\Controllers\Api\SupportChatController::class, 'getSession']);
        Route::get('/sessions/{id}/messages', [\App\Http\Controllers\Api\SupportChatController::class, 'getMessages']);
        Route::post('/sessions/{id}/messages', [\App\Http\Controllers\Api\SupportChatController::class, 'sendMessage']);
        Route::post('/sessions/{id}/close', [\App\Http\Controllers\Api\SupportChatController::class, 'closeSession']);
        Route::post('/sessions/{id}/reopen', [\App\Http\Controllers\Api\SupportChatController::class, 'reopenSession']);
    });
});

Route::post('/paymongo/webhook', [PaymongoWebhookController::class, 'handle']);


