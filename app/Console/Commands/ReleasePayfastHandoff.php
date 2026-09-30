<?php

namespace App\Console\Commands;

use App\Domain\Payments\Services\RegistrationPaymentService;
use App\Domain\Payments\Services\TeamPaymentService;
use App\Models\RegistrationOrder;
use App\Models\TeamPaymentOrder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ReleasePayfastHandoff extends Command
{
    protected $signature = 'payments:release-payfast-handoff
        {type : Exact order type: registration or team}
        {id : Exact order ID}
        {--evidence= : Safe provider evidence or reconciliation reference}
        {--operator= : Existing super-user ID authorizing the release}
        {--apply : Clear the unresolved handoff marker after validation}';

    protected $description = 'Preview or explicitly release one unresolved PayFast handoff after supervised provider reconciliation';

    public function handle(
        RegistrationPaymentService $registrationPayments,
        TeamPaymentService $teamPayments
    ): int {
        $type = strtolower(trim((string) $this->argument('type')));
        $id = filter_var($this->argument('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (! in_array($type, ['registration', 'team'], true) || $id === false) {
            $this->error('Specify the exact order type (registration or team) and a positive order ID.');

            return self::FAILURE;
        }

        /** @var Model $order */
        $order = $type === 'registration'
            ? RegistrationOrder::query()->with('items')->find($id)
            : TeamPaymentOrder::query()->find($id);

        if (! $order) {
            $this->error('The specified order was not found.');

            return self::FAILURE;
        }

        try {
            $type === 'registration'
                ? $registrationPayments->assertPayfastHandoffMayBeReleased($order)
                : $teamPayments->assertPayfastHandoffMayBeReleased($order);
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->first() ?? 'The handoff cannot be released.');

            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->info("PREVIEW: {$type} order {$id} is eligible. No data was changed.");

            return self::SUCCESS;
        }

        $evidenceReference = trim((string) $this->option('evidence'));
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/-]{2,119}$/', $evidenceReference)) {
            $this->error('Applying a release requires a safe 3-120 character provider evidence/reference.');

            return self::FAILURE;
        }
        $operatorId = filter_var($this->option('operator'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $operator = $operatorId === false ? null : \App\Models\User::query()->find($operatorId);
        if (! $operator?->hasRole('super-user')) {
            $this->error('Applying a release requires an existing super-user operator.');

            return self::FAILURE;
        }

        try {
            $type === 'registration'
                ? $registrationPayments->releaseUnresolvedPayfastHandoff($order, $operator, $evidenceReference)
                : $teamPayments->releaseUnresolvedPayfastHandoff($order, $operator, $evidenceReference);
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->first() ?? 'The handoff cannot be released.');

            return self::FAILURE;
        }

        $this->info("Released {$type} order {$id}. The order may now be re-submitted through the normal checkout.");

        return self::SUCCESS;
    }
}
