<?php

namespace App\Console\Commands;

use App\Domain\Entries\Services\EntryService;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class CorrectPrivateTrialAdminEntry extends Command
{
    protected $signature = 'interpro:correct-private-admin-entry
        {event} {category} {player} {nomination} {entry} {receipt}
        {--actor= : Existing reviewing super-user ID}
        {--reason= : Exact operator reason explaining the erroneous entry}
        {--confirm-no-funds-collected : Operator confirms no private funds were collected}
        {--confirmation= : Exact confirmation token from the fresh preview}
        {--apply : Apply only the unchanged reviewed correction}';

    protected $description = 'Preview or correct one erroneous private-admin trial entry, preserving history and restoring normal nomination checkout';

    public function handle(EntryService $entries): int
    {
        $actor = User::find($this->option('actor'));
        if (!$actor || !$actor->hasRole('super-user')) { $this->error('An existing super-user actor is required.'); return self::FAILURE; }
        if ($this->option('apply') && !$this->option('confirmation')) { $this->error('Apply requires a fresh exact preview confirmation.'); return self::FAILURE; }
        try {
            $result = $entries->correctPrivateTrialAdminEntry($actor, [
                'event_id' => $this->argument('event'), 'category_event_id' => $this->argument('category'), 'player_id' => $this->argument('player'),
                'nomination_id' => $this->argument('nomination'), 'entry_id' => $this->argument('entry'), 'receipt_id' => $this->argument('receipt'),
            ], (string) $this->option('reason'), (bool) $this->option('confirm-no-funds-collected'), $this->option('apply') ? (string) $this->option('confirmation') : null);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) foreach ($messages as $message) $this->error($message);
            return self::FAILURE;
        }
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $this->info($result['preview'] ? 'Preview only. No records changed.' : 'Exact correction applied or verified. No withdrawal, refund or email was created.');
        return self::SUCCESS;
    }
}