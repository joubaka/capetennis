<?php

namespace App\Console\Commands;

use App\Mail\SuspensionAlertMail;
use App\Models\Player;
use App\Models\SiteSetting;
use App\Services\DisciplinaryService;
use App\Services\MailAccountManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckDisciplinaryThresholds extends Command
{
    protected $signature   = 'disciplinary:check-thresholds';
    protected $description = 'Check all players for suspension threshold breaches and send admin alerts.';

    public function handle(DisciplinaryService $service, MailAccountManager $mailer): int
    {
        $adminEmail = SiteSetting::get('admin_email', config('mail.from.address'));

        $triggered = 0;

        Player::chunk(200, function ($players) use ($service, $mailer, $adminEmail, &$triggered) {
            foreach ($players as $player) {
                $suspension = $service->checkAndTriggerSuspension($player);

                if ($suspension) {
                    $triggered++;
                    $this->info("Suspension triggered for player #{$player->id} ({$player->full_name})");

                    try {
                        Mail::mailer($mailer->getMailer())
                            ->to($adminEmail)
                            ->send(new SuspensionAlertMail($player, $suspension));
                    } catch (\Throwable $e) {
                        $this->warn("Failed to send alert for player #{$player->id}: " . $e->getMessage());
                    }
                }
            }
        });

        $this->info("Done. {$triggered} new suspension(s) triggered.");

        return self::SUCCESS;
    }
}
