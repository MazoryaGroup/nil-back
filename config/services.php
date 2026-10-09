<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'group_id'  => env('TELEGRAM_GROUP_ID'),
    ],
    'smsir' => [
        'api_key' => env('SMSIR_API_KEY'),
        'line_number' => env('SMSIR_LINE_NUMBER'),

        'templates' => [
            'otp' => env('SMSIR_OTP_TEMPLATE_ID'),
            'booking_confirmed' => env('SMSIR_BOOKING_CONFIRMED_TEMPLATE_ID'),
            'booking_cancelled' => env('SMSIR_BOOKING_CANCELLED_TEMPLATE_ID'),
            'booking_rescheduled' => env('SMSIR_BOOKING_RESCHEDULED_TEMPLATE_ID'),
            'reminder_24h' => env('SMSIR_REMINDER_24H_TEMPLATE_ID'),
            'reminder_2h' => env('SMSIR_REMINDER_2H_TEMPLATE_ID'),
            'payment_success' => env('SMSIR_PAYMENT_SUCCESS_TEMPLATE_ID'),
            'payment_link' => env('SMSIR_TEMPLATE_PAYMENT_LINK'),
            'welcome' => (int) env('SMSIR_WELCOME_TEMPLATE_ID'),
        ],
    ],
    'zarinpal' => [
        'merchant_id' => env('ZARINPAL_MERCHANT_KEY'),
        'sandbox' => env('ZARINPAL_SANDBOX', true),
        'callback_url' => env('ZARINPAL_CALLBACK_URL'),
    ],
    'nil' => [
        'frontend_url' => env('NIL_FRONTEND_URL'),
        'payment_short_path' => env('NIL_PAYMENT_SHORT_PATH', '/p'),
    ],

];
