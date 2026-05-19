<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinanceAuditPaymentsCommand extends Command
{
    protected $signature = 'finance:audit-payments';

    protected $description = 'Report payment audit mismatches only';

    public function handle(): int
    {
        $issues = collect();

        if (DB::getSchemaBuilder()->hasTable('registration_orders')) {
            $issues = $issues->merge($this->auditRegistrationOrders());
        }

        if (DB::getSchemaBuilder()->hasTable('team_payment_orders')) {
            $issues = $issues->merge($this->auditTeamOrders());
        }

        if ($issues->isEmpty()) {
            return self::SUCCESS;
        }

        $this->table(['type', 'id', 'issue'], $issues->all());

        return self::FAILURE;
    }

    protected function auditRegistrationOrders(): Collection
    {
        return DB::table('registration_orders')
            ->select('id', 'wallet_reserved', 'wallet_debited', 'payfast_paid', 'payfast_pf_payment_id', 'payfast_amount_due', 'pay_status')
            ->get()
            ->flatMap(function ($row) {
                $issues = [];

                if ((int) $row->pay_status === 1 && (int) $row->payfast_paid !== 1 && (float) $row->wallet_reserved <= 0) {
                    $issues[] = ['type' => 'registration_order', 'id' => $row->id, 'issue' => 'paid_without_gateway_or_wallet'];
                }

                if ((float) $row->wallet_reserved > 0 && (int) $row->pay_status === 1 && (int) $row->wallet_debited !== 1) {
                    $issues[] = ['type' => 'registration_order', 'id' => $row->id, 'issue' => 'paid_with_reserved_wallet_not_debited'];
                }

                if ((int) $row->payfast_paid === 1 && empty($row->payfast_pf_payment_id) && (float) $row->payfast_amount_due > 0) {
                    $issues[] = ['type' => 'registration_order', 'id' => $row->id, 'issue' => 'payfast_paid_missing_pf_reference'];
                }

                return $issues;
            });
    }

    protected function auditTeamOrders(): Collection
    {
        return DB::table('team_payment_orders')
            ->select('id', 'wallet_reserved', 'wallet_debited', 'payfast_paid', 'payfast_pf_payment_id', 'payfast_amount_due', 'pay_status', 'refund_status', 'refund_net')
            ->get()
            ->flatMap(function ($row) {
                $issues = [];

                if ((int) $row->pay_status === 1 && (float) $row->wallet_reserved > 0 && (int) $row->wallet_debited !== 1) {
                    $issues[] = ['type' => 'team_payment_order', 'id' => $row->id, 'issue' => 'paid_with_reserved_wallet_not_debited'];
                }

                if ((int) $row->payfast_paid === 1 && empty($row->payfast_pf_payment_id) && (float) $row->payfast_amount_due > 0) {
                    $issues[] = ['type' => 'team_payment_order', 'id' => $row->id, 'issue' => 'payfast_paid_missing_pf_reference'];
                }

                if (($row->refund_status ?? null) === 'completed' && (float) ($row->refund_net ?? 0) <= 0) {
                    $issues[] = ['type' => 'team_payment_order', 'id' => $row->id, 'issue' => 'completed_refund_missing_positive_amount'];
                }

                return $issues;
            });
    }
}
