<?php

namespace App\Console\Commands;

use App\Domain\Payments\Services\TeamLatePaymentReconciliationService;
use App\Models\User;
use Illuminate\Console\Command;

final class ReconcileLateTeamPayment extends Command
{
    protected $signature = 'payments:reconcile-late-team {order : Exact original team checkout ID} {provider : Exact PayFast reference} {--actor= : Authorizing super-user ID} {--reason= : Reviewed reconciliation reason} {--apply : Apply the reviewed reconciliation; default is preview}';

    protected $description = 'Preview or explicitly reconcile an exact verified late team PayFast payment without a new charge';

    public function handle(TeamLatePaymentReconciliationService $service): int
    {
        try {
            $actor = User::findOrFail($this->option('actor'));
            $result = $service->reconcile((int) $this->argument('order'), (string) $this->argument('provider'), $actor,
                (string) $this->option('reason'), (bool) $this->option('apply'));
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->error(implode(' ', \Illuminate\Support\Arr::flatten($exception->errors())));
            return self::FAILURE;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            $this->error('An authorized super-user is required.');
            return self::FAILURE;
        }
    }
}
