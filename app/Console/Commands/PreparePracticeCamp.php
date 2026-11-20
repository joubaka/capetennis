<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PracticeCampPlanner;
use Illuminate\Console\Command;

class PreparePracticeCamp extends Command
{
    protected $signature = 'camp:prepare-2027 {--actor= : Existing local super-user ID}';

    protected $description = 'Prepare the private Cavaliers Practice Camp 2027 draft locally; never sends or publishes';

    public function handle(PracticeCampPlanner $planner): int
    {
        if (! app()->environment('local')) {
            $this->error('This setup command is local-only.');

            return self::FAILURE;
        }
        $actor = User::find($this->option('actor'));
        if (! $actor || ! $actor->hasRole('super-user')) {
            $this->error('Choose an existing local super-user with --actor.');

            return self::FAILURE;
        }
        $plan = $planner->create2027($actor);
        $this->info('Private camp event '.$plan->event_id.' ready: '.route('backend.practice-camps.show', $plan));

        return self::SUCCESS;
    }
}
