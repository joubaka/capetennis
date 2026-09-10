<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\TeamSelection\TeamSelectionInvitationService;
use Illuminate\Console\Command;

class ProcessTeamSelectionDeadlines extends Command
{
    protected $signature = 'team-selection:process-deadlines
        {event? : Optional team event ID}
        {--apply : Expire places and promote eligible reserves}';

    protected $description = 'Expire unanswered or unpaid regional team invitations and promote reserves';

    public function handle(TeamSelectionInvitationService $service): int
    {
        $eventId = $this->argument('event');
        if ($eventId !== null && (! ctype_digit((string) $eventId) || ! Event::whereKey((int) $eventId)->exists())) {
            $this->error('A valid event ID is required.');
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $rows = $service->processExpiredInvitations($eventId === null ? null : (int) $eventId, $apply);
        $this->info($apply ? 'Team-selection deadlines processed.' : 'Preview only: no records changed.');
        $this->table(['Invitation', 'Player', 'Previous status', 'Replacement'], array_map(fn (array $row) => [
            $row['invitation_id'], $row['player'], $row['previous_status'], $row['replacement_id'] ?? '—',
        ], $rows));
        $this->line('Expired places found: '.count($rows));
        if (! $apply && $rows !== []) {
            $this->warn('Re-run with --apply after reviewing this preview.');
        }

        return self::SUCCESS;
    }
}
