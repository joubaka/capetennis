<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\Masters\MastersInvitationService;
use Illuminate\Console\Command;

class ReconcileMastersPayments extends Command
{
    protected $signature = 'masters:reconcile-payments
        {event? : Optional Masters event ID; omit to scan all Masters invitations}
        {--apply : Apply the reported corrections}
        {--pending-minutes= : Minutes before an unpaid checkout returns to Register}';

    protected $description = 'Reconcile paid Masters entries and return stale unpaid checkouts to Register';

    public function handle(MastersInvitationService $service): int
    {
        $eventId = $this->argument('event');
        if ($eventId !== null && (!ctype_digit((string) $eventId) || !Event::whereKey((int) $eventId)->exists())) {
            $this->error('A valid event ID is required.');
            return self::FAILURE;
        }

        $minutes = $this->option('pending-minutes');
        $minutes = $minutes === null
            ? (int) config('masters.pending_payment_minutes', 60)
            : max(1, (int) $minutes);
        $apply = (bool) $this->option('apply');

        $rows = $service->reconcilePaymentStates(
            $eventId === null ? null : (int) $eventId,
            $minutes,
            $apply,
        );

        $this->info($apply ? 'Masters payment reconciliation applied.' : 'Preview only: no records changed.');
        $this->line("Unpaid checkout timeout: {$minutes} minutes");
        $this->table(
            ['Invitation', 'Player', 'Action', 'Order', 'Registration'],
            array_map(fn (array $row) => [
                $row['invitation_id'],
                $row['player'],
                $row['action'],
                $row['order_id'] ?? '—',
                $row['registration_id'] ?? '—',
            ], $rows),
        );
        $this->line('Records found: '.count($rows));

        if (!$apply && $rows !== []) {
            $this->warn('Re-run with --apply after reviewing this preview.');
        }

        return self::SUCCESS;
    }
}
