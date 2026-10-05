<?php

namespace App\Services;

use App\Models\BulkEmailLog;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Email;
use Throwable;
use WeakMap;
/** Event mail snapshots and metadata-only tracking for unrelated system mail. Logging must never interrupt or retry a send. */
class OutboundMailHistory
{
    private WeakMap $messages;
    private array $attemptIds = [];
    private bool $queuedContext = false;
    private array $queuedIds = [];
    public function __construct()
    {
        $this->messages = new WeakMap();
    }
    /** Called before Laravel serializes the actual queued mailable. No command decoding. */
    public function queuePayload(string $connection, ?string $queue, array $payload): array
    {
        $job = $payload['data']['command'] ?? null;
        if ($job instanceof \App\Jobs\SendRecoveryPaymentMailJob) {
            return $this->recoveryQueuePayload($job, $payload);
        }
        if (!$job instanceof \Illuminate\Mail\SendQueuedMailable) {
            return [];
        }
        $mail = $job->mailable;
        if (isset($mail->viewData['outbound_mail_log_id']) || isset($mail->viewData['outbound_mail_history_ids'])) {
            return [];
        }
        try {
            $data = array_merge(get_object_vars($mail), $mail->viewData);
            $eventId = $this->eventId($data);
            if (!$eventId) {
                return [];
            }
            $ids = [];
            $addresses = collect([...$mail->to, ...$mail->cc, ...$mail->bcc])->unique(fn($row) => strtolower($row['address']));
            if ($addresses->count() > 1000) {
                return [];
            }
            foreach ($addresses as $address) {
                $log = BulkEmailLog::create([
                    'mail_type' => 'system_mail',
                    'recipient_email' => $address['address'],
                    'recipient_name' => $address['name'] ?? '',
                    'status' => 'queued',
                    'queued_at' => now(),
                    'payload' => [
                        'event_id' => $eventId,
                        'created_by' => auth()->id(),
                        'system_initiated' => auth()->id() === null,
                        'queue_uuid' => $payload['uuid'],
                        'queue_state' => 'prepared',
                        'financial_metadata_only' => $this->financialData($data),
                        'rendered_subject' => mb_substr($mail->subject ?? 'Queued system email', 0, 255),
                    ],
                ]);
                $ids[] = $log->id;
            }
            $mail->with('outbound_mail_history_ids', $ids);
            return ['outbound_mail_history_ids' => $ids];
        } catch (Throwable) {
            return [];
        }
    }
    /** Read-only attribution for the existing canonical financial-mail job. */
    private function recoveryQueuePayload(\App\Jobs\SendRecoveryPaymentMailJob $job, array $payload): array
    {
        try {
            $recovery = \App\Models\RegistrationPaymentRecovery::with('user', 'order.items.category_event')->find($job->recoveryId);
            $eventId = $recovery ? $this->eventId(['recovery' => $recovery]) : null;
            if (! $eventId || ! $recovery?->user?->email) return [];
            $actor = $recovery->mail_queued_by ?? auth()->id();
            $log = BulkEmailLog::create([
                'mail_type' => 'system_mail',
                'recipient_email' => $recovery->user->email,
                'recipient_name' => $recovery->user->name,
                'status' => 'queued',
                'queued_at' => now(),
                'payload' => [
                    'event_id' => $eventId, 'created_by' => $actor,
                    'system_initiated' => $actor === null,
                    'queue_uuid' => $payload['uuid'], 'queue_state' => 'prepared',
                    'financial_metadata_only' => true, 'rendered_subject' => 'Payment recovery email',
                ],
            ]);
            return ['outbound_mail_history_ids' => [$log->id]];
        } catch (Throwable) {
            return [];
        }
    }

    private function trackedIds(mixed $value): array
    {
        if (!is_array($value) || count($value) > 1000) {
            return [];
        }
        foreach ($value as $id) {
            if (!is_int($id) || $id < 1) {
                return [];
            }
        }
        return array_values(array_unique($value));
    }
    public function queued(\Illuminate\Queue\Events\JobQueued $event): void
    {
        $ids = $this->trackedIds($event->payload()['outbound_mail_history_ids'] ?? []);
        if ($ids === []) {
            return;
        }
        try {
            foreach (BulkEmailLog::whereIn('id', $ids)->get() as $log) {
                $log->update(['payload' => [...$log->payload, 'queue_state' => 'enqueued']]);
            }
        } catch (Throwable) {
        }
    }
    public function processing(\Illuminate\Queue\Events\JobProcessing $event): void
    {
        $this->resetAttempt();
        $this->queuedContext = true;
        $this->attemptIds = $this->trackedIds($event->job->payload()['outbound_mail_history_ids'] ?? []);
        $this->queuedIds = $this->attemptIds;
        if ($this->attemptIds === []) {
            return;
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($event): void {
            $logs = BulkEmailLog::whereIn('id', $this->attemptIds)->orderBy('id')->lockForUpdate()->get();
            foreach ($logs as $log) {
                if ($log->status === 'failed' && !$log->sent_at && !$log->accepted_at && !data_get($log->payload, 'transport_started')) {
                    $log->update(['status' => 'queued', 'queued_at' => now(), 'failed_at' => null, 'error_message' => null]);
                }
            }
            if ($logs->contains(fn($log) => $log->status !== 'queued')) {
                $event->job->delete();
                if ($logs->contains(fn($log) => in_array($log->status, ['sent', 'acceptance_unknown'], true))) {
                    foreach ($logs->whereIn('status', ['queued', 'sending']) as $log) {
                        $log->update(['status' => 'acceptance_unknown']);
                    }
                }
                $this->resetAttempt();
                return;
            }
            foreach ($logs as $log) {
                $log->update(['status' => 'sending']);
            }
        });
    }
    public function processed(\Illuminate\Queue\Events\JobProcessed $event): void
    {
        if ($this->attemptIds === []) {
            return;
        }
        try {
            // A held/vetoed message returns normally but never reaches MessageSending.
            foreach (BulkEmailLog::whereIn('id', $this->attemptIds)->where('status', 'sending')->get() as $log) {
                if ($event->job->isReleased()) {
                    $log->update(['status' => 'queued']);
                } elseif (data_get($log->payload, 'transport_started')) {
                    $log->update(['status'=>'acceptance_unknown','error_message'=>'Transport ran but acceptance evidence could not be saved. Verify before retrying.']);
                } else {
                    $log->markAsSkipped('Transport did not run. The message may require event review.');
                }
            }
            $this->resetAttempt();
        } catch (Throwable) {
        }
    }
    public function held(MessageSending $event): void
    {
        try {
            foreach (BulkEmailLog::whereIn('id', $event->data['outbound_mail_history_ids'] ?? $this->queuedIds)->get() as $log) {
                $log->markAsSkipped('Held for event message review. No email was sent.');
            }
        } catch (Throwable) {
        }
    }
    public function sending(MessageSending $event): void
    {
        try {
            if (isset($event->data['outbound_mail_log_id'])) {
                // The existing sender owns its reviewed log and transport evidence.
                return;
            }
            if ($ids = $event->data['outbound_mail_history_ids'] ?? $this->queuedIds) {
                foreach (BulkEmailLog::whereIn('id', $ids)->get() as $log) {
                    if (in_array($log->status, ['sent', 'acceptance_unknown', 'skipped'], true)) {
                        continue;
                    }
                    $snapshot = ['rendered_subject' => mb_substr($event->message->getSubject() ?? '', 0, 255)];
                    if (!data_get($log->payload, 'financial_metadata_only')) {
                        $snapshot += ['rendered_html' => $event->message->getHtmlBody(), 'rendered_text' => $event->message->getTextBody()];
                    }
                    $log->update(['status' => 'sending', 'payload' => [...$log->payload, ...$snapshot, 'transport_started' => true]]);
                }
                $this->messages[$event->message] = $ids;
                return;
            }
            if (isset($this->messages[$event->message])) {
                return;
            }
            $ids = [];
            $eventId = $this->eventId($event->data);
            $payload = ['rendered_subject' => mb_substr($event->message->getSubject() ?? '', 0, 255)];
            if ($eventId) {
                $payload += ['event_id' => $eventId, 'created_by' => auth()->id(), 'system_initiated' => auth()->id() === null, 'financial_metadata_only' => $this->financialData($event->data)];
                if (!$this->financialData($event->data)) {
                    $payload += ['rendered_html' => $event->message->getHtmlBody(), 'rendered_text' => $event->message->getTextBody()];
                }
            }
            foreach ($this->recipients($event->message) as $address) {
                $log = BulkEmailLog::create(['mail_type' => 'system_mail', 'recipient_email' => mb_substr($address->getAddress(), 0, 255), 'recipient_name' => mb_substr($address->getName(), 0, 255), 'status' => 'sending', 'payload' => $payload, 'queued_at' => now()]);
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
            $original = ($event->data['message'] ?? null) instanceof \Illuminate\Mail\Message ? $event->data['message']->getSymfonyMessage() : $event->message;
            $ids = $this->messages[$original] ?? [];
            if ($ids !== []) {
                foreach (BulkEmailLog::whereIn('id', $ids)->where('status', 'sending')->get() as $log) {
                    $log->update(['status' => 'sent', 'sent_at' => now(), 'evidence_status' => 'unverified']);
                }
            }
        } catch (Throwable) {
            // A successful transport result must not become a failed queue job.
        }
    }
    public function resetAttempt(): void
    {
        $this->attemptIds = [];
        $this->queuedContext = false;
        $this->queuedIds = [];
        $this->messages = new WeakMap();
    }
    public function interruptedAttempt(): void
    {
        if ($this->attemptIds === []) {
            $this->resetAttempt();
            return;
        }
        try {
            foreach (BulkEmailLog::whereIn('id', $this->attemptIds)->where('status', 'sending')->get() as $log) {
                if (data_get($log->payload, 'transport_started') || $this->queuedIds === []) {
                    $log->update(['status' => 'acceptance_unknown', 'error_message' => 'Transport acceptance is uncertain. Verify with the mail provider before retrying.']);
                } else {
                    $log->markAsFailed('The queued message could not be prepared. No transport attempt was recorded.');
                }
            }
        } catch (Throwable) {
        }
        $this->resetAttempt();
    }
    /** Only trusted model data with exactly one event establishes attribution. */
    private function eventId(array $data): ?int
    {
        $ids = [];
        $values = $this->modelValues($data);
        if ($values === null) {
            return null;
        }
        foreach ($values as $value) {
            if ($value instanceof \App\Models\Event) {
                $ids[] = $value->id;
            } elseif ($value instanceof \App\Models\CategoryEvent) {
                $ids[] = $value->event_id;
            } elseif ($value instanceof \App\Models\CategoryEventRegistration) {
                $ids[] = $value->categoryEvent?->event_id;
            } elseif ($value instanceof \App\Models\TeamSelectionInvitation || $value instanceof \App\Models\InterprovincialTrialInvitation) {
                $ids[] = $value->event_id;
            } elseif ($value instanceof \App\Models\MastersInvitation) {
                $ids[] = $value->batch?->event_id;
            } elseif ($value instanceof \App\Models\TeamPaymentOrder) {
                $ids[] = $value->event_id;
            } elseif ($value instanceof \App\Models\RegistrationOrder) {
                $ids = array_merge($ids, $value->items()->with('category_event')->get()->pluck('category_event.event_id')->all());
            } elseif ($value instanceof \App\Models\RegistrationPaymentRecovery) {
                $ids = array_merge($ids, $value->order?->items()->with('category_event')->get()->pluck('category_event.event_id')->all() ?? []);
            } elseif ($value instanceof \App\Models\Registration) {
                $ids = array_merge($ids, $value->categoryEventRegistrations()->with('categoryEvent')->get()->pluck('categoryEvent.event_id')->all());
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        return count($ids) === 1 ? (int) $ids[0] : null;
    }
    private function modelValues(array $data): ?array
    {
        $values = [];
        $visited = 0;
        $walk = function ($value, int $depth) use (&$walk, &$values, &$visited): bool {
            if (++$visited > 1000 || $depth > 8) {
                return false;
            }
            if ($value instanceof \Illuminate\Database\Eloquent\Model) {
                $values[] = $value;
            } elseif (is_array($value) || $value instanceof \Illuminate\Support\Collection) {
                foreach ($value as $child) {
                    if (!$walk($child, $depth + 1)) {
                        return false;
                    }
                }
            }
            return true;
        };
        return $walk($data, 0) ? $values : null;
    }
    private function financialData(array $data): bool
    {
        $values = $this->modelValues($data);
        if ($values === null) {
            return true;
        }
        foreach ($values as $value) {
            if ($value instanceof \App\Models\CategoryEventRegistration || $value instanceof \App\Models\Registration || $value instanceof \App\Models\RegistrationOrder || $value instanceof \App\Models\TeamPaymentOrder || $value instanceof \App\Models\RegistrationPaymentRecovery) {
                return true;
            }
        }
        return false;
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
