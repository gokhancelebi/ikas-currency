<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Arama motoru engelleme
    |--------------------------------------------------------------------------
    |
    | robots.txt, X-Robots-Tag başlığı ve bilinen bot User-Agent'ları.
    |
    */

    'block_search_engine_bots' => env('BLOCK_SEARCH_ENGINE_BOTS', true),

    'noindex' => env('SITE_NOINDEX', true),

    /*
    |--------------------------------------------------------------------------
    | Oturum — mutlak süre (dakika)
    |--------------------------------------------------------------------------
    |
    | Girişten bu kadar dakika sonra kullanıcı tekrar giriş yapmalı (hareket
    | etse bile). 0 = yalnızca SESSION_LIFETIME (hareketsizlik) geçerli.
    |
    */

    'session_absolute_lifetime' => (int) env('SESSION_ABSOLUTE_LIFETIME', 0),

    /*
    |--------------------------------------------------------------------------
    | HTTP rate limit
    |--------------------------------------------------------------------------
    */

    'rate_limit_enabled' => env('RATE_LIMIT_ENABLED', true),

    // Genel web istekleri (IP veya giriş yapmış kullanıcı ID)
    'web_per_minute' => (int) env('RATE_LIMIT_PER_MINUTE', 120),

    // Giriş, kayıt, şifre sıfırlama POST istekleri (IP)
    'auth_per_minute' => (int) env('AUTH_RATE_LIMIT_PER_MINUTE', 20),

    // Başarısız giriş denemesi (e-posta + IP)
    'login_max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
    'login_decay_minutes' => (int) env('LOGIN_DECAY_MINUTES', 1),

    // /cron-refresh (IP)
    'cron_refresh_per_minute' => (int) env('CRON_REFRESH_RATE_LIMIT', 10),

];
