<?php

namespace App\Console\Commands;

use App\Domain\Payments\Services\TeamReplacementPaymentCorrectionService;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class CorrectTeamReplacementPayment extends Command
{
    protected $signature = 'finance:correct-team-replacement
        {event : Exact event ID} {team : Exact team ID} {source : Original paid order ID} {replacement : Closed unpaid replacement order ID}
        {source-player : Original player ID} {target-player : Replacement player ID}
        {--actor= : Existing super-user ID} {--reason= : Reviewed correction reason}
        {--fingerprint= : Exact fingerprint returned by the read-only preview} {--apply : Apply the reviewed correction atomically}';

    protected $description = 'Preview or explicitly correct an unused wallet refund into original paid replacement coverage; sends no payment or message';

    public function handle(TeamReplacementPaymentCorrectionService $service): int
    {
        $actor = User::find($this->option('actor'));
        if (!$actor || !$actor->hasRole('super-user')) {
            $this->error('An existing super-user actor is required.');
            return self::FAILURE;
        }
        $ids = ['event_id' => $this->argument('event'), 'team_id' => $this->argument('team'),
            'source_order_id' => $this->argument('source'), 'replacement_order_id' => $this->argument('replacement'),
            'source_player_id' => $this->argument('source-player'), 'target_player_id' => $this->argument('target-player')];
        try {
            $result = $this->option('apply')
                ? $service->apply($actor, $ids, (string) $this->option('reason'), (string) $this->option('fingerprint'))
                : $service->preview($actor, $ids, (string) $this->option('reason'));
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) foreach ($messages as $message) $this->error($message);
            return self::FAILURE;
        }
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $this->info($this->option('apply') ? 'Correction recorded, or the exact prior correction verified. No provider call or message sent.' : 'Preview only. No financial or roster records changed.');
        return self::SUCCESS;
    }
}
