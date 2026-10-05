<?php

namespace App\Console\Commands;

use App\Models\BulkEmailLog;
use App\Services\EventMailLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RecordEventMailIssues extends Command
{
    protected $signature = 'mail:record-event-issues';
    protected $description = 'Record persistent sender alerts for failed or stalled event emails without sending mail';

    public function handle(EventMailLogService $service): int
    {
        if (! Schema::hasTable('event_mail_issues')) return self::SUCCESS;
        BulkEmailLog::where('created_at', '>=', now()->subDays(30))->where(function ($q) {
            $q->whereIn('status', ['failed', 'skipped', 'acceptance_unknown'])->orWhere(fn ($stalled) => $stalled->whereIn('status', ['queued', 'sending'])->where('updated_at', '<', now()->subHour()));
        })->chunkById(100, fn ($logs) => $logs->each(fn ($log) => $service->recordIssue($log)));
        return self::SUCCESS;
    }
}
