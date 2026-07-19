<?php

return [

    'client_id' => env('IKAS_CLIENT_ID'),

    'client_secret' => env('IKAS_CLIENT_SECRET'),

    'store_domain' => rtrim((string) env('IKAS_STORE_DOMAIN', ''), '/').'/',

    'oauth_url' => env('IKAS_OAUTH_URL', 'https://api.myikas.com/api/admin/oauth/token'),

    'graphql_url' => env('IKAS_GRAPHQL_URL', 'https://api.myikas.com/api/v2/admin/graphql'),

    'image_upload_url' => env('IKAS_IMAGE_UPLOAD_URL', 'https://api.myikas.com/api/v1/admin/product/upload/image'),

    // DEV skips concurrent sync lock
    'app_mode' => env('IKAS_APP_MODE', 'PROD'),

    'altinkaynak' => [
        'currency_url' => env('ALTINKAYNAK_CURRENCY_URL', 'https://static.altinkaynak.com/public/Currency'),
        'gold_url' => env('ALTINKAYNAK_GOLD_URL', 'https://static.altinkaynak.com/public/Gold'),
    ],

    'rates' => [
        'currency_xml' => public_path('kurlar.xml'),
        'gold_xml' => public_path('altin.xml'),
        'max_age_seconds' => (int) env('IKAS_RATES_MAX_AGE_SECONDS', 3600),
    ],

    'storage_path' => storage_path('app/ikas-sync'),

    'live_tests' => (bool) env('IKAS_LIVE_TESTS', false),

    'test_tag' => env('IKAS_TEST_TAG', 'SYNC_TEST'),

    'throttle_seconds' => (int) env('IKAS_THROTTLE_SECONDS', 1),

    'default_price_type' => env('IKAS_DEFAULT_PRICE_TYPE', 'TL'),

    'admin_mail_notifications_enabled' => filter_var(
        env('ADMIN_MAIL_NOTIFICATIONS_ENABLED', false),
        FILTER_VALIDATE_BOOL
    ),

    'admin_email' => env('ADMIN_EMAIL'),

    'admin_mail_throttle_seconds' => (int) env('ADMIN_MAIL_THROTTLE_SECONDS', 300),

    'price_types' => [
        'TL',
        'USD',
        'EUR',
        'Has Toptan',
        '22 Ayar Bilezik',
        'Gram Toptan',
    ],

];
