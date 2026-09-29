<?php

return [
    /*
     * اینجا همان Property ID را وارد می‌کنیم.
     * پکیج‌های قدیمی به آن View ID می‌گفتند.
     */
    'view_id' => env('ANALYTICS_PROPERTY_ID'),

    /*
     * مسیر کلید JSON
     */
    'service_account_credentials_json' => storage_path('app/analytics/service-account-credentials.json'),

    /*
     * تنظیمات کش
     */
    'cache_lifetime_in_minutes' => 60 * 24,
    'cache' => [
        'store' => 'file',
    ],
];
