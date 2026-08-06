<?php

namespace Tests\Unit;

use App\Jobs\SendBulkEmailJob;
use Illuminate\Queue\Middleware\RateLimited;
use Tests\TestCase;

class SendBulkEmailJobRateLimitTest extends TestCase
{
    public function test_bulk_email_jobs_use_the_outbound_mail_rate_limiter(): void
    {
        config(['mail.bulk_mail.rate_per_second' => 14]);

        $middleware = (new SendBulkEmailJob(1))->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(RateLimited::class, $middleware[0]);
        $this->assertSame(14, config('mail.bulk_mail.rate_per_second'));
    }
}
