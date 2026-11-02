<?php

namespace App\Console\Commands;

use App\Services\Assistant\CapesterReceiver;
use Illuminate\Console\Command;

class CapesterReceive extends Command
{
    protected $signature = 'assistant:capester-receive';
    protected $description = 'Receive one reviewed support request into the private local Capester inbox';

    public function handle(CapesterReceiver $receiver): int
    {
        $result = $receiver->receive();
        if ($result === 'failed') {
            $this->error('Capester handoff failed. Check local receiver configuration and access separately.');
            return self::FAILURE;
        }
        $this->info(match ($result) {
            'received' => 'Support request delivered to the private local inbox for review. No fix was executed.',
            'hello_passed' => 'Synthetic connection test passed and was acknowledged.',
            'hello_failed' => 'Synthetic connection test failed and was acknowledged.',
            default => 'No eligible request is waiting.',
        });
        return $result === 'hello_failed' ? self::FAILURE : self::SUCCESS;
    }
}
