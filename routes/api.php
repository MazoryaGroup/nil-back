<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientProfileController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\WaitingListController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\GalleryController;


Route::prefix('v1/auth')->group(function () {

    // Register
    Route::post('/register/send-code', [
        AuthController::class,
        'registerSendCode'
    ]);

    Route::post('/register/verify', [
        AuthController::class,
        'registerVerify'
    ]);

    // Login with Phone
    Route::post('/login/phone/send-code', [
        AuthController::class,
        'loginPhoneSendCode'
    ]);

    Route::post('/login/phone/verify', [
        AuthController::class,
        'loginPhoneVerify'
    ]);
    Route::post('/login', [
        AuthController::class,
        'login'
    ]);
    Route::post('/forgot-password/send-code', [
        AuthController::class,
        'forgotPasswordSendCode'
    ]);
    Route::post('/forgot-password/verify', [
        AuthController::class,
        'forgotPasswordVerify'
    ]);
    Route::post('/forgot-password/reset', [
        AuthController::class,
        'forgotPasswordReset'
    ]);
    Route::post('/refresh', [
        AuthController::class,
        'refresh'
    ]);

    Route::middleware('auth:api')->group(function () {

        Route::get('/me', [
            AuthController::class,
            'me'
        ]);
        Route::post('/logout', [
            AuthController::class,
            'logout'
        ]);
        Route::post('/refresh', [
            AuthController::class,
            'refresh'
        ]);
        Route::get('/profile', [
            ClientProfileController::class,
            'show'
        ]);
        Route::put('/profile', [
            ClientProfileController::class,
            'update'
        ]);
        Route::post('/profile/change-phone/send-code', [ClientProfileController::class, 'changePhoneSendCode']);

        Route::post('/profile/change-phone/verify', [ClientProfileController::class, 'changePhoneVerify']);
    });

});



Route::middleware('auth:api')->prefix('v1/bookings')->group(function () {
    Route::get('/', [BookingController::class, 'index']);
    Route::get('/{id}', [BookingController::class, 'show']);
    Route::post('/', [BookingController::class, 'store']);
    Route::post('/{id}/cancel', [BookingController::class, 'cancel']);
    Route::put('/{id}/reschedule', [BookingController::class, 'reschedule']);
});

Route::middleware('auth:api')->prefix('v1/availability')->group(function () {
    Route::get('/', [AvailabilityController::class, 'index']);
});

Route::middleware('auth:api')->prefix('v1/payments')->group(function () {
    Route::post('/bookings/{bookingId}/deposit', [PaymentController::class, 'deposit']);
    Route::post('/bookings/{bookingId}/start', [PaymentController::class, 'start']);
});
Route::get('/v1/payments/zarinpal/callback', [
    PaymentController::class,
    'callback'
]);


Route::prefix('waiting-list')->group(function () {

    Route::post('/', [WaitingListController::class, 'store']);

});

Route::post('/messages', [ContactController::class, 'store']);


Route::prefix('v1')->group(function () {

    Route::get('/blogs', [BlogController::class, 'index']);

    Route::get('/blogs/{slug}', [BlogController::class, 'show']);

});

Route::prefix('v1/gallery')->group(function () {
    Route::get('/categories', [GalleryController::class, 'categories']);
    Route::get('/', [GalleryController::class, 'index']);
    Route::get('/{id}', [GalleryController::class, 'show']);
});
