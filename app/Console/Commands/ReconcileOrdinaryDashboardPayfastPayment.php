<?php

namespace App\Console\Commands;

use App\Domain\Payments\Services\OrdinaryDashboardPayfastReconciliationService;
use App\Models\User;
use Illuminate\Console\Command;

final class ReconcileOrdinaryDashboardPayfastPayment extends Command
{
    protected $signature = 'payments:reconcile-ordinary-dashboard {order : Exact original ordinary checkout ID} {--actor= : Reviewing super-user ID} {--evidence= : Local authenticated PayFast dashboard evidence JSON} {--reason= : Reviewed recovery reason} {--confirmation= : Fresh actor-bound preview token; omit for read-only preview}';
    protected $description = 'Preview or reconcile an on-time ordinary individual PayFast basket missing its receipt after automatic expiry';

    public function handle(OrdinaryDashboardPayfastReconciliationService $service): int
    {
        try {
            $path = (string) $this->option('evidence');
            if (! is_file($path) || filesize($path) > 16384) throw new \RuntimeException('A local evidence JSON file up to 16 KiB is required.');
            $evidence = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($evidence)) throw new \RuntimeException('Evidence must be a JSON object.');
            $result = $service->run((int) $this->argument('order'), User::findOrFail($this->option('actor')), $evidence,
                (string) $this->option('reason'), $this->option('confirmation'));
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
