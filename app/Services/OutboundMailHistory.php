<?php

namespace App\Services;

use App\Models\BulkEmailLog;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Email;
use Throwable;
use WeakMap;

/** Metadata-only observability. Logging must never interrupt or retry a send. */
class OutboundMailHistory
{
    private WeakMap $messages;

    private array $attemptIds = [];

    public function __construct()
    {
        $this->messages = new WeakMap;
    }

    public function sending(MessageSending $event): void
    {
        try {
            if (isset($event->data['outbound_mail_log_id'])) {
                // The existing sender owns its reviewed log and transport evidence.
                return;
            }
            if (isset($this->messages[$event->message])) {
                return;
            }
            $ids = [];
            foreach ($this->recipients($event->message) as $address) {
                $log = BulkEmailLog::create([
                    'mail_type' => 'system_mail',
                    'recipient_email' => mb_substr($address->getAddress(), 0, 255),
                    'recipient_name' => mb_substr($address->getName(), 0, 255),
                    'status' => 'sending',
                    'payload' => ['rendered_subject' => mb_substr($event->message->getSubject() ?? '', 0, 255)],
                    'queued_at' => now(),
                ]);
                $ids[] = $log->id;
                $this->attemptIds[] = $log->id;
                $this->messages[$event->message] = $ids;
            }
            $this->messages[$event->message] = $ids;
        } catch (Throwable) {
            // Observability failure must not change mail delivery semantics.
        }
    }

    public function sent(MessageSent $event): void
    {
        try {
            // Symfony transports clone the Email. Laravel retains its original
            // message wrapper in event data, so correlate that object instead.
            $original = ($event->data['message'] ?? null) instanceof \Illuminate\Mail\Message
                ? $event->data['message']->getSymfonyMessage() : $event->message;
            $ids = $this->messages[$original] ?? [];
            if ($ids !== []) {
                BulkEmailLog::whereIn('id', $ids)->where('status', 'sending')->update([
                    'status' => 'sent', 'sent_at' => now(), 'evidence_status' => 'unverified',
                ]);
            }
        } catch (Throwable) {
            // A successful transport result must not become a failed queue job.
        }
    }

    public function resetAttempt(): void
    {
        $this->attemptIds = [];
        $this->messages = new WeakMap;
    }

    public function interruptedAttempt(): void
    {
        try {
            BulkEmailLog::whereIn('id', $this->attemptIds)->where('status', 'sending')
                ->update(['status' => 'acceptance_unknown']);
        } catch (Throwable) {
        }
        $this->resetAttempt();
    }

    private function recipients(Email $message): array
    {
        $recipients = [];
        foreach (array_merge($message->getTo(), $message->getCc(), $message->getBcc()) as $address) {
            $recipients[strtolower($address->getAddress())] = $address;
        }

        return array_values($recipients);
    }
}
