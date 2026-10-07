<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MailAccountManager
{
  /**
   * Mail transports available for managed application email.
   *
   * SES is the sole production transport. Keeping SMTP accounts out of this
   * list prevents queued mail from bypassing MAIL_MAILER and connecting to
   * the legacy Mailgun SMTP endpoint.
   */
  protected array $accounts = ['ses'];

  public function getMailer(): string
  {
    // Local development must always use the configured Mailtrap sandbox.
    // Rotating to noreply1/noreply2 would use production-style accounts that
    // are intentionally not configured in the local .env file.
    if (
      config('mail.default') === 'smtp'
      && config('mail.mailers.smtp.host') === 'sandbox.smtp.mailtrap.io'
    ) {
      return 'smtp';
    }

    // Provider quotas and outbound-mail throttling govern sending. The daily
    // counter is telemetry, not a local quota that can silently exclude a roster.
    $account = $this->accounts[0];
    try {
      $key = "mail_count_{$account}";
      Cache::add($key, 0, now()->endOfDay());
      Cache::increment($key);
    } catch (\Throwable) {
      Log::warning('[MailAccountManager] Daily mail count could not be recorded.');
    }

    return $account;
  }

  public function resetDailyCounts(): void
  {
    foreach ($this->accounts as $account) {
      Cache::forget("mail_count_{$account}");
    }
  }

  public function getStatus(): array
  {
    return collect($this->accounts)->mapWithKeys(function ($account) {
      return [$account => Cache::get("mail_count_{$account}", 0)];
    })->toArray();
  }
}
