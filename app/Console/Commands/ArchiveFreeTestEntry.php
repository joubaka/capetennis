<?php

namespace App\Console\Commands;

use App\Domain\Entries\Services\EntryService;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ArchiveFreeTestEntry extends Command
{
    protected $signature = 'entries:archive-free-test
        {event : Exact event ID} {category : Exact category-event ID} {player : Exact player ID}
        {entry : Exact category-entry ID} {receipt : Exact Admin Entry receipt ID}
        {--actor= : Existing super-user ID} {--reason= : Reviewed cleanup reason}
        {--evidence= : Evidence that this was an unpaid zero-money test}
        {--retain-entry= : Exact separate completed free frontend entry to preserve unchanged}
        {--fingerprint= : Exact fingerprint returned by the read-only preview}
        {--apply : Archive only the reviewed test history atomically}';

    protected $description = 'Preview or reversibly archive one unused withdrawn zero-money admin test; never wipes an event or sends messages';

    public function handle(EntryService $entries): int
    {
        $actor = User::find($this->option('actor'));
        if (! $actor || ! $actor->hasRole('super-user')) {
            $this->error('An existing super-user actor is required.');
            return self::FAILURE;
        }
        if ($this->option('apply') && ! preg_match('/^[a-f0-9]{64}$/D', (string) $this->option('fingerprint'))) {
            $this->error('Apply requires the exact fingerprint from a fresh read-only preview.');
            return self::FAILURE;
        }
        try {
            $ids = [
                'event_id' => $this->argument('event'), 'category_event_id' => $this->argument('category'),
                'player_id' => $this->argument('player'), 'entry_id' => $this->argument('entry'),
                'receipt_id' => $this->argument('receipt'),
            ];
            if ($this->option('retain-entry') !== null) $ids['retained_entry_id'] = $this->option('retain-entry');
            $result = $entries->archiveFreeTestHistory($actor, $ids, (string) $this->option('reason'), (string) $this->option('evidence'),
                $this->option('apply') ? (string) $this->option('fingerprint') : null);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) $this->error($message);
            }
            return self::FAILURE;
        }
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $this->info($result['preview'] ? 'Preview only. No records changed.' : 'Exact test history archived, or prior archive verified. No payment or message sent.');
        return self::SUCCESS;
    }
}
