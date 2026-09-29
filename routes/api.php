<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\ClientApiController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderApiController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\WaitingListController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\PasswordApiController;
use App\Http\Controllers\Api\TelegramController;


// ================= AUTH =================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/login-otp', [AuthController::class, 'loginWithOtp']);
Route::post('/verify-login-otp', [AuthController::class, 'verifyLoginOtp']);


// ================= CLIENT =================
Route::post('/change-password', [ClientApiController::class, 'changePassword']);


// ================= PRODUCTS (PUBLIC) =================
Route::post('/details-product', [ProductController::class, 'detailsByPost']);
Route::get('/products', [ProductController::class, 'showByget']);
Route::get('/drops', [ProductController::class, 'drops']);
Route::get('/categories', [ProductController::class, 'categories']);

Route::prefix('products')->group(function () {
    Route::get('/latest', [OrderApiController::class, 'latestProducts']);
    Route::get('/daily-discounts', [OrderApiController::class, 'dailyDiscounts']);
    Route::get('/popular', [OrderApiController::class, 'popularProducts']);
    Route::get('/highest-discounts', [OrderApiController::class, 'highestDiscounts']);
});


// ================= PAYMENT =================

// PAYMENT
Route::middleware('auth:api')->group(function () {
    Route::post('/payment/request', [PaymentController::class, 'requestPayment']);
});

Route::get('/payment/verify', [PaymentController::class, 'verifyPayment']);

// ================= USER PROFILE =================
Route::middleware('auth:api')->group(function () {

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/update-profile', [ProfileController::class, 'update']);

    // addresses
    Route::prefix('addresses')->group(function () {
        Route::get('/', [AddressController::class, 'index']);
        Route::post('/', [AddressController::class, 'store']);
        Route::put('/{id}', [AddressController::class, 'update']);
        Route::delete('/{id}', [AddressController::class, 'destroy']);
    });

    // orders
    Route::prefix('orders')->group(function () {
        Route::post('/', [OrderApiController::class, 'store']);
        Route::get('/{id}', [OrderApiController::class, 'show']);
    });

    Route::get('/user-orders', [OrderApiController::class, 'ordersByClient']);

    // cart
    Route::prefix('cart')->group(function () {
        Route::get('/count', [OrderApiController::class, 'cartCount']);
        Route::get('/items', [OrderApiController::class, 'cartItems']);
        Route::post('/add', [OrderApiController::class, 'addToCart']);
        Route::delete('/remove/{id}', [OrderApiController::class, 'removeFromCart']);
        Route::put('/update/{id}', [OrderApiController::class, 'updateCartItem']);
        Route::delete('/clear', [OrderApiController::class, 'clearCart']);
    });
});


Route::post('/check-password', [PasswordApiController::class, 'check']);

// ================= WAITING LIST =================
Route::prefix('waiting-list')->group(function () {
    Route::get('/', [WaitingListController::class, 'index']);
    Route::get('/{id}', [WaitingListController::class, 'show']);
});

Route::post('/messages', [ContactController::class, 'store']);
// ================= RATE LIMITED =================
Route::middleware('throttle:5,1')->group(function () {

    Route::post('/waiting-list', [WaitingListController::class, 'store']);
    Route::post('/chat/send', [ChatController::class, 'send']);
});


// ================= CHAT =================
Route::get(
    '/chat/messages-for-visitor/{conversation:uuid}',
    [ChatController::class, 'messagesForVisitor']
);


// ================= TELEGRAM =================
Route::post('/telegram/webhook', [TelegramController::class, 'webhook']);


// ================= ADMIN =================
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', fn(Request $request) => $request->user());

    Route::prefix('messages')->group(function () {
        Route::get('/', [ContactController::class, 'index']);
        Route::get('/{id}', [ContactController::class, 'show']);
    });

});
