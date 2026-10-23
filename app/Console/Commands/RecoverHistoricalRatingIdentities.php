<?php

namespace App\Console\Commands;

use App\Models\{Event, User};
use App\Services\Performance\HistoricalIdentityRecoveryService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class RecoverHistoricalRatingIdentities extends Command
{
    protected $signature = 'ratings:recover-identities {event} {manifest} {--recorded-by=} {--apply}';
    protected $description = 'Validate or explicitly apply reviewed documentary identities for exact historical rating match sides';

    public function handle(HistoricalIdentityRecoveryService $service): int
    {
        $path = realpath($this->argument('manifest'));
        if (!$path || !is_file($path) || !is_readable($path) || filesize($path) > 1048576) {
            $this->error('Choose a readable JSON manifest smaller than 1 MiB.');
            return self::FAILURE;
        }
        try {
            $manifest = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($manifest)) { throw new \JsonException('Expected an object.'); }
            $result = $service->import(Event::findOrFail($this->argument('event')), User::findOrFail($this->option('recorded-by')), $manifest, (bool) $this->option('apply'));
        } catch (ValidationException $error) {
            foreach ($error->errors() as $messages) { foreach ($messages as $message) { $this->error($message); } }
            return self::FAILURE;
        } catch (\JsonException|\Carbon\Exceptions\InvalidFormatException $error) {
            $this->error('Invalid manifest JSON or review timestamp.');
            return self::FAILURE;
        }
        $this->info(json_encode($result, JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
