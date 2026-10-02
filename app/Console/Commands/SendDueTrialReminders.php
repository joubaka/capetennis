<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Services\InterprovincialTrials\TrialCommunicationService;
class SendDueTrialReminders extends Command {
    protected $signature='trials:send-due-reminders';
    protected $description='Identify Trials reminders due for manual review without sending email';
    public function handle(TrialCommunicationService $service): int { $this->info($service->runDue().' reminders due for manual review; no emails queued.'); return self::SUCCESS; }
}
