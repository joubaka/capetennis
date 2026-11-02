<?php

namespace App\Console\Commands;

use App\Services\Assistant\AssistantRepairLibrary;
use Illuminate\Console\Command;
use JsonException;
use RuntimeException;

class AssistantRepairGuide extends Command
{
    protected $signature = 'assistant:repair-guide {query? : Search words or a repair ID}';

    protected $description = 'Read verified repair guidance without executing repairs or contacting AI';

    public function handle(AssistantRepairLibrary $library): int
    {
        try {
            $records = $library->search((string) $this->argument('query'));
        } catch (RuntimeException|JsonException $exception) {
            $this->error('Repair library could not be read: '.$exception->getMessage());
            return self::FAILURE;
        }
        $this->info('Read-only repair guidance. No repair was executed.');
        if ($records === []) {
            $this->line('No verified guide matches. Investigate before adding a new repair.');
        }
        foreach ($records as $record) {
            $this->newLine();
            $this->info($record['id'].' — '.$record['title']);
            foreach (['scope', 'symptoms', 'cause', 'fix', 'verification', 'limits', 'assistant_action'] as $field) {
                $this->line(ucfirst(str_replace('_', ' ', $field)).': '.$record[$field]);
            }
            $this->line('Sources: '.implode(', ', $record['sources']));
            $this->line('Tests: '.implode(', ', $record['tests']));
        }
        return self::SUCCESS;
    }
}
