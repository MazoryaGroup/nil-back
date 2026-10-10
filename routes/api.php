<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ClientProfileController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\GalleryController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OfflineSyncController;
use App\Http\Controllers\Api\OperatorAuthController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\WaitingListController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\ShortPaymentLinkController;
use App\Http\Controllers\Api\DiscountCodeController;


/*
|--------------------------------------------------------------------------
| API V1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Client Authentication
    |--------------------------------------------------------------------------
    */

    Route::prefix('auth')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Register
        |--------------------------------------------------------------------------
        */

        Route::post('/register/send-code', [
            AuthController::class,
            'registerSendCode',
        ]);

        Route::post('/register/verify', [
            AuthController::class,
            'registerVerify',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Login
        |--------------------------------------------------------------------------
        */

        Route::post('/login', [
            AuthController::class,
            'login',
        ]);

        Route::post('/login/phone/send-code', [
            AuthController::class,
            'loginPhoneSendCode',
        ]);

        Route::post('/login/phone/verify', [
            AuthController::class,
            'loginPhoneVerify',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Forgot Password
        |--------------------------------------------------------------------------
        */

        Route::post('/forgot-password/send-code', [
            AuthController::class,
            'forgotPasswordSendCode',
        ]);

        Route::post('/forgot-password/verify', [
            AuthController::class,
            'forgotPasswordVerify',
        ]);

        Route::post('/forgot-password/reset', [
            AuthController::class,
            'forgotPasswordReset',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Refresh Token
        |--------------------------------------------------------------------------
        */

        Route::post('/refresh', [
            AuthController::class,
            'refresh',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Protected Client Authentication
        |--------------------------------------------------------------------------
        */

        Route::middleware('auth:api')->group(function () {

            Route::get('/me', [
                AuthController::class,
                'me',
            ]);

            Route::post('/logout', [
                AuthController::class,
                'logout',
            ]);


            /*
            |--------------------------------------------------------------------------
            | Client Profile
            |--------------------------------------------------------------------------
            */

            Route::get('/profile', [
                ClientProfileController::class,
                'show',
            ]);

            Route::put('/profile', [
                ClientProfileController::class,
                'update',
            ]);

            Route::post('/profile/change-phone/send-code', [
                ClientProfileController::class,
                'changePhoneSendCode',
            ]);

            Route::post('/profile/change-phone/verify', [
                ClientProfileController::class,
                'changePhoneVerify',
            ]);
        });
    });

    /*
        |--------------------------------------------------------------------------
        | services - Client Protected
        |--------------------------------------------------------------------------
        */


    Route::get('/services', [ServiceController::class, 'index']);
    Route::get('/services/{id}', [ServiceController::class, 'show']);

    /*
        |--------------------------------------------------------------------------
        | staff - staff Protected
        |--------------------------------------------------------------------------
        */


    Route::get('/staff', [StaffController::class, 'index']);
    Route::get('/staff/{id}', [StaffController::class, 'show']);
    Route::get('/staff/{id}/schedule', [StaffController::class, 'schedule']);

    /*
    |--------------------------------------------------------------------------
    | Bookings - Client Protected
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:api')
        ->prefix('bookings')
        ->group(function () {

            Route::get('/', [
                BookingController::class,
                'index',
            ]);

            Route::post('/', [
                BookingController::class,
                'store',
            ]);

            Route::get('/{id}', [
                BookingController::class,
                'show',
            ]);

            Route::post('/{id}/cancel', [
                BookingController::class,
                'cancel',
            ]);

            Route::put('/{id}/reschedule', [
                BookingController::class,
                'reschedule',
            ]);
        });


    /*
    |--------------------------------------------------------------------------
    | Availability - Client Protected
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:api')
        ->prefix('availability')
        ->group(function () {

            Route::get('/', [
                AvailabilityController::class,
                'index',
            ]);
        });


    /*
    |--------------------------------------------------------------------------
    | Payments - Client Protected
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:api')
        ->prefix('payments')
        ->group(function () {

            Route::post('/bookings/{bookingId}/deposit', [
                PaymentController::class,
                'deposit',
            ]);

            Route::post('/bookings/{bookingId}/start', [
                PaymentController::class,
                'start',
            ]);

            Route::post('/bookings/{bookingId}/remaining', [PaymentController::class, 'remaining']);
            Route::post('/bookings/{bookingId}/remaining/start', [PaymentController::class, 'remainingStart']);
        });


    /*
    |--------------------------------------------------------------------------
    | Zarinpal Callback - Public
    |--------------------------------------------------------------------------
    */

    Route::get('/payments/zarinpal/callback', [
        PaymentController::class,
        'callback',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Blogs - Public
    |--------------------------------------------------------------------------
    */

    Route::prefix('blogs')->group(function () {

        Route::get('/', [
            BlogController::class,
            'index',
        ]);

        Route::get('/{slug}', [
            BlogController::class,
            'show',
        ]);
    });


    /*
    |--------------------------------------------------------------------------
    | Gallery - Public
    |--------------------------------------------------------------------------
    */

    Route::prefix('gallery')->group(function () {

        Route::get('/categories', [
            GalleryController::class,
            'categories',
        ]);

        Route::get('/', [
            GalleryController::class,
            'index',
        ]);

        Route::get('/{id}', [
            GalleryController::class,
            'show',
        ]);
    });


    /*
    |--------------------------------------------------------------------------
    | Notifications - Client Protected
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:api')
        ->prefix('notifications')
        ->group(function () {

            Route::get('/', [
                NotificationController::class,
                'index',
            ]);

            Route::get('/unread-count', [
                NotificationController::class,
                'unreadCount',
            ]);

            Route::put('/read-all', [
                NotificationController::class,
                'markAllAsRead',
            ]);

            Route::put('/{id}/read', [
                NotificationController::class,
                'markAsRead',
            ]);
        });
});


/*
|--------------------------------------------------------------------------
| Waiting List - Public
|--------------------------------------------------------------------------
|
| فعلاً مسیر قبلی حفظ شده تا فرانت نشکند:
| POST /api/waiting-list
|
*/

Route::post('/waiting-list', [
    WaitingListController::class,
    'store',
]);


/*
|--------------------------------------------------------------------------
| Contact - Public
|--------------------------------------------------------------------------
|
| فعلاً مسیر قبلی حفظ شده:
| POST /api/messages
|
*/

Route::post('/messages', [
    ContactController::class,
    'store',
]);


/*
|--------------------------------------------------------------------------
| Operator Authentication
|--------------------------------------------------------------------------
*/

Route::prefix('operator')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Login - Public
    |--------------------------------------------------------------------------
    */

    Route::post('/login', [
        OperatorAuthController::class,
        'login',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Operator Protected
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:operator')->group(function () {

        Route::get('/me', [
            OperatorAuthController::class,
            'me',
        ]);

        Route::post('/refresh', [
            OperatorAuthController::class,
            'refresh',
        ]);

        Route::post('/logout', [
            OperatorAuthController::class,
            'logout',
        ]);
    });
});


/*
|--------------------------------------------------------------------------
| Offline Sync - Operator Protected
|--------------------------------------------------------------------------
*/

Route::middleware('auth:operator')
    ->prefix('offline-sync')
    ->group(function () {

        Route::post('/', [
            OfflineSyncController::class,
            'sync',
        ]);

        Route::post('/batch', [
            OfflineSyncController::class,
            'batch',
        ]);

        Route::get('/{offlineId}', [
            OfflineSyncController::class,
            'status',
        ]);

        Route::post('/{offlineId}/retry', [
            OfflineSyncController::class,
            'retry',
        ]);
    });





Route::middleware('auth:api')->prefix('v1')->group(function () {

    Route::post(
        '/discount-codes/validate',
        [DiscountCodeController::class, 'validateCode']
    );

});

Route::get(
    '/v1/payments/resolve/{token}',
    [ShortPaymentLinkController::class, 'resolve']
)->middleware('throttle:30,1');
