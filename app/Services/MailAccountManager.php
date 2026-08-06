<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

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
  protected int $limit = 500; // per day limit per account

  public function getMailer(): string
  {
    foreach ($this->accounts as $account) {
      $key = "mail_count_{$account}";
      $count = Cache::get($key, 0);

      if ($count < $this->limit) {
        Cache::put($key, $count + 1, now()->endOfDay());
        \Log::info("[MailAccountManager] Using mailer: {$account} ({$count}/{$this->limit})");
        return $account;
      }
    }

    \Log::warning("[MailAccountManager] All mailers exhausted for today, falling back to log transport.");
    return 'log';
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
