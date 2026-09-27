<?php

namespace App\Console\Commands;

use App\Domain\Payments\Services\RegistrationPaymentRecoveryService;
use App\Models\RegistrationOrder;
use App\Models\User;
use Illuminate\Console\Command;

class PrepareRegistrationPaymentRecoveryCommand extends Command
{
    protected $signature = 'registrations:recovery-prepare {orders*} {--actor= : Super-user ID} {--confirm= : Hash from preview}';
    protected $description = 'Prepare confirmed registration payment recoveries; does not send mail';

    public function handle(RegistrationPaymentRecoveryService $service): int
    {
        $ids = collect($this->argument('orders'))->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        $actor = User::query()->find((int) $this->option('actor'));
        if (! $actor || ! $actor->hasRole('super-user')) return $this->commandFailure('A valid super-user --actor is required.');
        $recoveries = $service->prepareBatch($ids, $actor->id, (string) $this->option('confirm'));
        foreach ($recoveries as $recovery) {
            $this->line("Prepared recovery {$recovery->id} for order {$recovery->registration_order_id}; no email queued.");
        }
        return self::SUCCESS;
    }

    private function commandFailure(string $message): int { $this->error($message); return self::FAILURE; }
}
