<?php

namespace App\Lib\IkasSync;

use App\Mail\RatesSyncBlockedMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RatesFailureNotifier
{
    private const CACHE_KEY = 'ikas_rates_alert_sent_at';

    public function notify(string $reason): void
    {
        Log::warning('İkas sync blocked: rates not ready', ['reason' => $reason]);
        echo __('mail.sync_blocked_console', ['reason' => $reason]).PHP_EOL;

        if (! config('ikas.admin_mail_notifications_enabled')) {
            return;
        }

        $adminEmail = trim((string) config('ikas.admin_email'));
        if ($adminEmail === '') {
            Log::warning('ADMIN_EMAIL tanımlı değil; kur uyarısı e-posta ile gönderilmedi.');

            return;
        }

        $throttleSeconds = max(60, (int) config('ikas.admin_mail_throttle_seconds', 300));
        if (Cache::has(self::CACHE_KEY)) {
            return;
        }

        try {
            Mail::to($adminEmail)->send(new RatesSyncBlockedMail($reason));
            Cache::put(self::CACHE_KEY, now()->timestamp, $throttleSeconds);
        } catch (\Throwable $e) {
            Log::error('Kur uyarı e-postası gönderilemedi', [
                'email' => $adminEmail,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
