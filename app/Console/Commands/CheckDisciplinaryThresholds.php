<?php

namespace App\Console\Commands;

use App\Mail\SuspensionAlertMail;
use App\Models\Player;
use App\Models\SiteSetting;
use App\Services\BulkMailDispatcher;
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
        $suspensions = collect();

        // Collect all triggered suspensions first
        Player::chunk(200, function ($players) use ($service, &$triggered, &$suspensions) {
            foreach ($players as $player) {
                $suspension = $service->checkAndTriggerSuspension($player);

                if ($suspension) {
                    $triggered++;
                    $suspensions->push([
                        'player' => $player,
                        'suspension' => $suspension,
                    ]);
                    $this->info("Suspension triggered for player #{$player->id} ({$player->full_name})");
                }
            }
        });

        // Send admin alerts via BulkMailDispatcher if there are suspensions
        if ($suspensions->isNotEmpty() && $adminEmail) {
            $dispatcher = app(BulkMailDispatcher::class);

            try {
                // Dispatch one email per suspension to admin (throttled)
                foreach ($suspensions as $item) {
                    $stats = $dispatcher->dispatch(
                        mailType: 'suspension_alert',
                        related: $item['suspension'],
                        recipients: [$adminEmail],
                        payload: [
                            'player_id' => $item['player']->id,
                            'suspension_id' => $item['suspension']->id,
                        ],
                        allowDuplicates: false
                    );

                    $this->line("  → Admin alert queued for player #{$item['player']->id}");
                }
            } catch (\Throwable $e) {
                $this->warn("Failed to queue suspension alerts: " . $e->getMessage());
            }
        }

        $this->info("Done. {$triggered} new suspension(s) triggered.");

        return self::SUCCESS;
    }
}
