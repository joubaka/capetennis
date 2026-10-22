<?php

namespace App\Console\Commands;

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\Event;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ConfirmRegionalFinancialRecipient extends Command
{
    protected $signature = 'finances:confirm-regional-recipient {event : Local event ID} {confirmation : Path to explicit financial-only confirmation JSON} {--confirmed-by= : Target director super-user who confirms the accounts are the same person} {--apply : Record after reviewing the preview}';

    protected $description = 'Preview or record a LOCAL event-only financial recipient confirmation; changes no account access or money records';

    public function handle(FinancialLedgerService $ledger): int
    {
        $path = realpath($this->argument('confirmation'));
        if (! $path || ! is_file($path) || ! is_readable($path) || filesize($path) > 16384) {
            $this->error('Choose a readable confirmation JSON file smaller than 16 KiB.');
            return self::FAILURE;
        }
        try {
            $data = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($data)) throw new \JsonException('Expected a JSON object.');
            $result = $ledger->recordRegionalFinancialRecipientLink(Event::findOrFail($this->argument('event')),
                User::findOrFail($this->option('confirmed-by')), $data, (bool) $this->option('apply'));
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) foreach ($messages as $message) $this->error($message);
            return self::FAILURE;
        } catch (\JsonException) {
            $this->error('The confirmation must be valid JSON.');
            return self::FAILURE;
        }
        $this->info($result['preview'] ? 'Confirmation validated. Preview only; no records changed.'
            : ($result['created'] ? 'Event-only financial recipient confirmation recorded. No access or money changed.'
                : 'This exact confirmation was already recorded; no records changed.'));
        return self::SUCCESS;
    }
}
