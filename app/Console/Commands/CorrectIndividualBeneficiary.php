<?php

namespace App\Console\Commands;

use App\Domain\Entries\Services\EntryService;
use App\Models\User;
use Illuminate\Console\Command;

class CorrectIndividualBeneficiary extends Command
{
    protected $signature = 'entries:correct-individual-beneficiary {order} {target-player} {--actor=} {--reason=} {--confirmation=}';
    protected $description = 'Preview an audited correction of an unplayed Interpro paid registration; apply only with its unchanged preview confirmation';

    public function handle(EntryService $entries): int
    {
        $actor = User::findOrFail((int) $this->option('actor'));
        $result = $entries->correctIndividualBeneficiary($actor, (int) $this->argument('order'), (int) $this->argument('target-player'),
            (string) $this->option('reason'), $this->option('confirmation'));
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
