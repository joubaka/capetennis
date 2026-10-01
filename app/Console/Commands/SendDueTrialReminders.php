<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Services\InterprovincialTrials\TrialCommunicationService;
class SendDueTrialReminders extends Command {
    protected $signature='trials:send-due-reminders';
    protected $description='Queue approved Trials reminders after checking current recipient status';
    public function handle(TrialCommunicationService $service): int { $this->info($service->runDue().' schedules processed.'); return self::SUCCESS; }
}
