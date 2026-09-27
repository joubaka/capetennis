<?php

namespace App\Console\Commands;

use App\Domain\Payments\Services\RegistrationPaymentRecoveryService;
use App\Jobs\SendRecoveryPaymentMailJob;
use App\Models\RegistrationPaymentRecovery;
use App\Models\User;
use Illuminate\Console\Command;

class QueueRegistrationPaymentRecoveryMailCommand extends Command
{
    protected $signature = 'registrations:recovery-mail {recoveries* : Exact recovery IDs} {--actor= : Super-user ID} {--confirm= : Queue confirmation hash}';
    protected $description = 'Queue deduplicated recovery emails after a separate explicit confirmation';

    public function handle(RegistrationPaymentRecoveryService $service): int
    {
        $ids = collect($this->argument('recoveries'))->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        $recoveries = RegistrationPaymentRecovery::query()->whereIn('id', $ids)->orderBy('id')->get();
        if ($recoveries->count() !== count($ids) || $recoveries->contains(fn ($recovery) => $recovery->mail_queued_at || $recovery->status === 'paid')) return $this->commandFailure('Every requested recovery must exist and be unsent/unpaid.');
        $hash = $service->mailBatchHash($recoveries->all());
        if (! $this->option('confirm')) {
            $this->table(['Recovery', 'Recipient', 'Event', 'Players', 'Categories', 'Amount', 'Contact', 'Expires', 'Template'], $recoveries->map(function ($recovery) {
                $s = $recovery->mail_snapshot; $masked = preg_replace('/(^.).*(@.*$)/', '$1***$2', $s['recipient']) ?: '(missing)';
                return [$recovery->id, $masked, $s['event'], implode(' / ', $s['players']), implode(' / ', $s['categories']), 'R'.$s['amount'], $s['contact'] ?: '(not set)', $s['expires_at'], $s['template']];
            })->all());
            $this->line('Queue confirmation hash: '.$hash); return self::SUCCESS;
        }
        if (! hash_equals($hash, (string) $this->option('confirm'))) return $this->commandFailure('Confirmation hash mismatch.');
        $actor = User::query()->find((int) $this->option('actor'));
        if (! $actor || ! $actor->hasRole('super-user')) return $this->commandFailure('A valid super-user --actor is required.');
        \Illuminate\Support\Facades\DB::transaction(function () use ($recoveries, $actor, $hash, $service): void {
            foreach ($recoveries as $recovery) {
                $locked = RegistrationPaymentRecovery::query()->lockForUpdate()->findOrFail($recovery->id);
                $service->validatePrepared($locked);
                $service->assertMailSnapshotCurrent($locked);
                $service->authorizeMail($locked, $actor->id);
            }
        });
        foreach ($recoveries as $recovery) {
            SendRecoveryPaymentMailJob::dispatch($recovery->id)->afterCommit();
            $this->line("Queued recovery {$recovery->id}.");
        }
        return self::SUCCESS;
    }

    private function commandFailure(string $message): int { $this->error($message); return self::FAILURE; }
}
