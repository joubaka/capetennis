<?php

namespace Tests\Unit;

use App\Services\MailAccountManager;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class MailAccountManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }
    public function test_mailtrap_sandbox_always_uses_the_primary_smtp_mailer(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'sandbox.smtp.mailtrap.io',
        ]);

        Cache::put('mail_count_smtp', 500);

        $this->assertSame('smtp', (new MailAccountManager())->getMailer());
        $this->assertSame(500, Cache::get('mail_count_smtp'));
    }
    public function test_exhausted_managed_transports_do_not_fall_back_to_the_log_transport(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'mail.capetennis.co.za',
        ]);

        Cache::put('mail_count_ses', 500);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('All configured mail transports have reached their daily limit.');

        (new MailAccountManager())->getMailer();
    }
}
