<?php

namespace App\Jobs;

use App\Models\EventCommunicationBatch;
use App\Services\EventAnnouncementService;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class QueueEventAnnouncementEmails implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $batchId) {}

    public function handle(EventAnnouncementService $announcements): void
    {
        $announcements->dispatchScheduled($this->batchId);
    }

    public function failed(Throwable $exception): void
    {
        EventCommunicationBatch::whereKey($this->batchId)->where('options->source', 'announcement')
            ->whereNotNull('options->scheduled_for')->where('status', 'scheduled')
            ->update(['status' => 'failed', 'options->announcement_mail_issue' => 'Participant emails could not be prepared. Review the queue before retrying.']);
    }
}
