<?php

namespace App\Console\Commands;

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\Event;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportHistoricalClothingRefund extends Command
{
    protected $signature = 'clothing:import-historical-refund {event : Local event ID} {snapshot : Path to verified source JSON} {--recorded-by= : Local super-user importing the evidence} {--apply : Apply after reviewing the preview}';

    protected $description = 'Preview or import verified historical completed external refund evidence into the LOCAL ct database only; sends no money or messages';

    public function handle(FinancialLedgerService $ledger): int
    {
        $path = realpath($this->argument('snapshot'));
        if (! $path || ! is_file($path) || ! is_readable($path) || filesize($path) > 262144) {
            $this->error('Choose a readable verified JSON snapshot smaller than 256 KiB.');
            return self::FAILURE;
        }
        try {
            $snapshot = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
            if (! is_array($snapshot)) throw new \JsonException('Expected a JSON object.');
            $result = $ledger->importHistoricalClothingRefund(
                Event::findOrFail($this->argument('event')), User::findOrFail($this->option('recorded-by')),
                $snapshot, (bool) $this->option('apply')
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) foreach ($messages as $message) $this->error($message);
            return self::FAILURE;
        } catch (\JsonException $exception) {
            $this->error('The snapshot must be valid JSON.');
            return self::FAILURE;
        }
        if ($result['preview']) {
            $this->info('Source evidence validated. Preview only; no records changed.');
        } elseif ($result['created']) {
            $this->info('Historical refund evidence imported locally as refund #'.$result['refund']->id.'. No payment or message was sent.');
        } else {
            $this->info('This exact source was already imported as refund #'.$result['refund']->id.'; no records changed.');
        }
        return self::SUCCESS;
    }
}
