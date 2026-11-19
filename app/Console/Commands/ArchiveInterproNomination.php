<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\InterprovincialTrials\NominationArchiveService;
use Illuminate\Console\Command;

class ArchiveInterproNomination extends Command
{
    protected $signature = 'interpro:archive-nomination {event} {category} {nomination} {--actor=} {--reason=} {--restore} {--confirmation=}';
    protected $description = 'Preview or confirm reversible nomination visibility after withdrawal; preserve all payment and registration history';

    public function handle(NominationArchiveService $service): int
    {
        $result = $service->run(User::findOrFail((int) $this->option('actor')), (int) $this->argument('event'), (int) $this->argument('category'),
            (int) $this->argument('nomination'), $this->option('restore') ? 'restore' : 'archive', (string) $this->option('reason'), $this->option('confirmation'));
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
