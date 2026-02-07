<?php

namespace App\Listeners;

use App\Events\AnnouncementPost;
use App\Mail\Announcement_mail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use App\Services\MailAccountManager;
use Illuminate\Support\Facades\Log;

class SendAnouncementEmail implements ShouldQueue
{
  use InteractsWithQueue;

  public function handle(AnnouncementPost $event)
  {
    $data = $event->data;

    // 🧩 Pick mailer (smtp, noreply1, noreply2)
    $mailer = app(MailAccountManager::class)->getMailer();

    $fromAddress = match ($mailer) {
      'noreply1' => 'noreply1@capetennis.co.za',
      'noreply2' => 'noreply2@capetennis.co.za',
      default => 'noreply@capetennis.co.za',
    };

    $fromName = 'Cape Tennis';

    Log::info('[AnnouncementMail] Sending', [
      'to' => $data['email'] ?? '(none)',
      'mailer' => $mailer,
      'from' => $fromAddress,
    ]);

    try {
      Mail::mailer($mailer)
        ->to($data['email'])
        ->send(
          (new Announcement_mail($data))
            ->from($fromAddress, $fromName)
            ->replyTo('info@capetennis.co.za', 'Cape Tennis')
        );

      Log::info('[AnnouncementMail] ✅ Sent', ['to' => $data['email']]);
    } catch (\Throwable $e) {
      Log::error('[AnnouncementMail] ❌ Failed', [
        'to' => $data['email'],
        'error' => $e->getMessage(),
      ]);
    }
  }
}
