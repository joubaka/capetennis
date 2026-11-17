<?php

namespace App\Console\Commands;

use App\Domain\Payments\Services\LateIndividualPayfastReconciliationService;
use App\Models\User;
use Illuminate\Console\Command;

final class ReconcileLateIndividualPayfastPayment extends Command
{
    protected $signature = 'payments:reconcile-late-individual {order : Original expired checkout ID} {--actor= : Reviewing super-user ID} {--provider= : Exact recorded PayFast reference} {--reason= : Reviewed reason} {--confirmation= : Fresh preview token; omit for read-only preview}';
    protected $description = 'Preview or reconcile one verified late individual PayFast receipt to its original ordinary registration';

    public function handle(LateIndividualPayfastReconciliationService $service): int
    {
        try {
            $result = $service->run((int) $this->argument('order'), User::findOrFail($this->option('actor')),
                (string) $this->option('provider'), (string) $this->option('reason'), $this->option('confirmation'));
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->error(implode(' ', \Illuminate\Support\Arr::flatten($exception->errors())));
        } catch (\Throwable $exception) {
            $this->error('Reconciliation blocked: '.$exception->getMessage());
        }
        return self::FAILURE;
    }
}
