<?php

namespace Tests\Unit;

use App\Services\MailAccountManager;
use Illuminate\Support\Facades\Cache;
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
    public function test_large_roster_sends_keep_using_the_managed_transport_after_500_attempts(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'mail.capetennis.co.za',
        ]);

        Cache::put('mail_count_ses', 500);

        $manager = new MailAccountManager;
        for ($i = 0; $i < 100; $i++) {
            $this->assertSame('ses', $manager->getMailer());
        }
        $this->assertSame(600, Cache::get('mail_count_ses'));
    }

    public function test_mail_count_failure_does_not_block_the_managed_transport(): void
    {
        config(['mail.default' => 'ses']);
        Cache::shouldReceive('add')->once()->andThrow(new \RuntimeException('Unavailable telemetry cache'));

        $this->assertSame('ses', (new MailAccountManager)->getMailer());
    }
}
