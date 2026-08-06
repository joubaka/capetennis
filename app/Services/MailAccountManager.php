<?php

namespace App\Services;

use RuntimeException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MailAccountManager
{
  protected array $accounts = ['smtp', 'noreply1', 'noreply2'];
  protected int $limit = 500; // per day limit per account

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

    foreach ($this->accounts as $account) {
      $key = "mail_count_{$account}";
      $count = Cache::get($key, 0);

      if ($count < $this->limit) {
        Cache::put($key, $count + 1, now()->endOfDay());
        Log::info("[MailAccountManager] Using mailer: {$account} ({$count}/{$this->limit})");
        return $account;
      }
    }

    // Never silently use the log transport here. Callers treat a transport
    // that returns successfully as sent, which would create false delivery
    // records while no email had left the application.
    Log::error('[MailAccountManager] All SMTP accounts exhausted for today.');

    throw new RuntimeException('All configured SMTP accounts have reached their daily limit.');
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
