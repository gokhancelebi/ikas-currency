<?php

return [

    'access_token' => env('SHOPIFY_ACCESS_TOKEN'),

    'store_domain' => rtrim((string) env('SHOPIFY_STORE_DOMAIN', ''), '/').'/',

    'api_version' => env('SHOPIFY_API_VERSION', '2026-07'),

    // DEV skips concurrent sync lock
    'app_mode' => env('SHOPIFY_APP_MODE', 'PROD'),

    'altinkaynak' => [
        'currency_url' => env('ALTINKAYNAK_CURRENCY_URL', 'https://static.altinkaynak.com/public/Currency'),
        'gold_url' => env('ALTINKAYNAK_GOLD_URL', 'https://static.altinkaynak.com/public/Gold'),
    ],

    // Public XML paths (UI reads these via ProductController)
    'rates' => [
        'currency_xml' => public_path('kurlar.xml'),
        'gold_xml' => public_path('altin.xml'),
        // UI: kurlar bu süreden eskiyse hata bandı gösterilir (saniye)
        'max_age_seconds' => (int) env('SHOPIFY_RATES_MAX_AGE_SECONDS', 3600),
    ],

    'storage_path' => storage_path('app/shopify-sync'),

    'live_tests' => (bool) env('SHOPIFY_LIVE_TESTS', false),

    'test_tag' => env('SHOPIFY_TEST_TAG', 'SYNC_TEST'),

    'throttle_seconds' => (int) env('SHOPIFY_THROTTLE_SECONDS', 1),

    // Yeni sync edilen ürün/varyantların varsayılan maliyet birimi (TL, USD, EUR, …)
    'default_price_type' => env('SHOPIFY_DEFAULT_PRICE_TYPE', 'TL'),

    // Kur/sync hatalarında bildirim (ADMIN_MAIL_NOTIFICATIONS_ENABLED=true gerekir)
    'admin_mail_notifications_enabled' => filter_var(
        env('ADMIN_MAIL_NOTIFICATIONS_ENABLED', false),
        FILTER_VALIDATE_BOOL
    ),

    // Kur/sync hatalarında bildirim alacak yönetici e-postası
    'admin_email' => env('ADMIN_EMAIL'),

    // Aynı kur uyarısı en fazla bu aralıkta bir mail gider (saniye, varsayılan 5 dk)
    'admin_mail_throttle_seconds' => (int) env('ADMIN_MAIL_THROTTLE_SECONDS', 300),

    // Panelde fiyat türü seçenekleri
    'price_types' => [
        'TL',
        'USD',
        'EUR',
        'Has Toptan',
        '22 Ayar Bilezik',
        'Gram Toptan',
    ],

];
