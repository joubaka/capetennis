<?php

namespace App\Console\Commands;

use App\Models\EventAssistantConversation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PruneEventAssistant extends Command
{
    protected $signature = 'assistant:prune {--apply : Delete expired conversations and their child records} {--limit=1000 : Maximum conversations per run}';

    protected $description = 'Bounded physical retention cleanup for expired event assistant conversations';

    public function handle(): int
    {
        if (! Schema::hasTable('event_assistant_conversations')) {
            $this->info('Assistant tables are not installed; nothing to prune.');
            return self::SUCCESS;
        }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
        if ($limit === false) {
            $this->error('Choose a limit from 1 to 10000.');
            return self::FAILURE;
        }
        $ids = EventAssistantConversation::where('expires_at', '<=', now())->orderBy('id')->limit($limit)->pluck('id');
        if (! $this->option('apply')) {
            $this->info($ids->count().' expired conversations in this batch. Preview only; use --apply to delete.');
            return self::SUCCESS;
        }
        // Database foreign keys cascade to messages, proposals and message feedback.
        $deleted = EventAssistantConversation::whereIn('id', $ids)->where('expires_at', '<=', now())->delete();
        $this->info($deleted.' expired conversations deleted.');

        return self::SUCCESS;
    }
}
