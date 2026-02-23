<?php

namespace App\Listeners;

use App\Events\AnnouncementPost;
use App\Mail\AnnouncementMail;
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
    Log::debug('[AnnouncementMail] 🚀 Listener triggered', [
      'job_id' => $this->job?->getJobId(),
      'attempt' => $this->attempts(),
      'queue' => $this->job?->getQueue(),
    ]);

    $data = $event->data;

    Log::debug('[AnnouncementMail] 📦 Event data received', [
      'has_email' => isset($data['email']),
      'email' => $data['email'] ?? '(missing)',
      'event_name' => $data['event'] ?? '(none)',
      'data_keys' => array_keys($data),
    ]);

    if (empty($data['email'])) {
      Log::error('[AnnouncementMail] ❌ No email address provided, aborting');
      return;
    }

    $mailer = app(MailAccountManager::class)->getMailer();

    $fromAddress = match ($mailer) {
      'noreply1' => 'noreply1@capetennis.co.za',
      'noreply2' => 'noreply2@capetennis.co.za',
      default => 'noreply@capetennis.co.za',
    };

    $fromName = 'Cape Tennis';

    Log::info('[AnnouncementMail] 📧 Preparing to send', [
      'to' => $data['email'],
      'mailer' => $mailer,
      'from' => $fromAddress,
      'from_name' => $fromName,
    ]);

    try {
      $mailable = (new AnnouncementMail($data))
        ->from($fromAddress, $fromName)
        ->replyTo('info@capetennis.co.za', 'Cape Tennis');

      Log::debug('[AnnouncementMail] 📝 Mailable created', [
        'class' => get_class($mailable),
        'subject' => $mailable->envelope()->subject ?? '(unknown)',
      ]);

      Mail::mailer($mailer)->to($data['email'])->send($mailable);

      Log::info('[AnnouncementMail] ✅ Sent successfully', [
        'to' => $data['email'],
        'mailer' => $mailer,
      ]);

    } catch (\Throwable $e) {
      Log::error('[AnnouncementMail] ❌ Failed to send', [
        'to' => $data['email'],
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
      ]);

      throw $e; // Re-throw so the job fails and can be retried
    }
  }

  /**
   * Handle a job failure.
   */
  public function failed(AnnouncementPost $event, \Throwable $exception): void
  {
    Log::critical('[AnnouncementMail] 💀 Job failed permanently', [
      'email' => $event->data['email'] ?? '(unknown)',
      'error' => $exception->getMessage(),
      'attempts' => $this->attempts(),
    ]);
  }
}
