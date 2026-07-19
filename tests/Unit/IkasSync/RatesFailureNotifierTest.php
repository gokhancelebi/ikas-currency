<?php

namespace Tests\Unit\IkasSync;

use App\Lib\IkasSync\RatesFailureNotifier;
use App\Mail\RatesSyncBlockedMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RatesFailureNotifierTest extends TestCase
{
    public function test_sends_email_to_admin_when_configured(): void
    {
        Mail::fake();
        Cache::flush();

        config([
            'ikas.admin_mail_notifications_enabled' => true,
            'ikas.admin_email' => 'admin@example.com',
        ]);

        (new RatesFailureNotifier())->notify('Test sebep');

        Mail::assertSent(RatesSyncBlockedMail::class, function (RatesSyncBlockedMail $mail) {
            return $mail->reason === 'Test sebep'
                && $mail->hasTo('admin@example.com');
        });
    }

    public function test_skips_email_when_admin_not_configured(): void
    {
        Mail::fake();
        config([
            'ikas.admin_mail_notifications_enabled' => true,
            'ikas.admin_email' => '',
        ]);

        (new RatesFailureNotifier())->notify('Test sebep');

        Mail::assertNothingSent();
    }

    public function test_skips_email_when_notifications_disabled(): void
    {
        Mail::fake();
        config([
            'ikas.admin_mail_notifications_enabled' => false,
            'ikas.admin_email' => 'admin@example.com',
        ]);

        (new RatesFailureNotifier())->notify('Test sebep');

        Mail::assertNothingSent();
    }

    public function test_throttles_repeated_alerts(): void
    {
        Mail::fake();
        Cache::flush();

        config([
            'ikas.admin_mail_notifications_enabled' => true,
            'ikas.admin_email' => 'admin@example.com',
        ]);

        $notifier = new RatesFailureNotifier();
        $notifier->notify('İlk');
        $notifier->notify('İkinci');

        Mail::assertSentCount(1);
        $this->assertNotNull(Cache::get('ikas_rates_alert_sent_at'));
    }
}
