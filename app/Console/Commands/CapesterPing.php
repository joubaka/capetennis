<?php

namespace App\Console\Commands;

use App\Services\Assistant\CapesterConnection;
use Illuminate\Console\Command;

class CapesterPing extends Command
{
    protected $signature = 'assistant:capester-ping';

    protected $description = 'Send a fixed local Codex connectivity handshake without application data';

    public function handle(CapesterConnection $connection): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This connection check is available only in local or testing environments.');
            return self::FAILURE;
        }
        if (! $connection->ping()) {
            $this->error('Capester connection check failed. Check the local Codex installation and sign-in separately.');
            return self::FAILURE;
        }
        $this->info('CAPESTER_PONG — local Codex connection verified. No application data was sent.');
        return self::SUCCESS;
    }
}
