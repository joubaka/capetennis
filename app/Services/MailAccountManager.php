<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class MailAccountManager
{
  protected array $accounts = ['smtp', 'noreply1', 'noreply2'];
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
