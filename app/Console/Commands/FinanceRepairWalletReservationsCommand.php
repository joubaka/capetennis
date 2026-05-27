<?php

namespace App\Console\Commands;

use App\Models\RegistrationOrder;
use App\Models\Wallet;
use App\Services\Wallet\Exceptions\DuplicateTransactionException;
use App\Services\Wallet\Exceptions\InsufficientFundsException;
use App\Services\Wallet\WalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * finance:repair-wallet-reservations
 *
 * Detects orders where:
 *   payfast_paid = 1
 *   wallet_reserved > 0
 *   wallet_debited = 0
 *
 * For each:
 *  - If user wallet exists and has sufficient balance → debit it and mark wallet_debited = 1
 *  - Otherwise → zero-out the phantom reservation (wallet_reserved = 0) with audit log
 *
 * Options:
 *   --dry-run : Report only, no mutations.
 *   --confirm : Actually perform repairs.
 */
class FinanceRepairWalletReservationsCommand extends Command
{
    protected $signature = 'finance:repair-wallet-reservations
                            {--dry-run : Report affected orders without making changes}
                            {--confirm : Confirm you want to perform the repairs}';

    protected $description = 'Repair orders where wallet was reserved but never debited after PayFast payment.';

    public function handle(WalletService $walletService): int
    {
        $isDryRun  = $this->option('dry-run');
        $isConfirm = $this->option('confirm');

        if (!$isDryRun && !$isConfirm) {
            $this->error('You must pass --dry-run or --confirm.');
            return 1;
        }

        $orders = RegistrationOrder::with(['user.wallet', 'items'])
            ->where('payfast_paid', true)
            ->where('wallet_debited', false)
            ->where('wallet_reserved', '>', 0)
            ->get();

        if ($orders->isEmpty()) {
            $this->info('✅ No wallet reservation corruption found.');
            return 0;
        }

        $this->warn(sprintf('⚠  Found %d order(s) with un-debited wallet reservations:', $orders->count()));

        $report = [];

        foreach ($orders as $order) {
            $reserved = (float) $order->wallet_reserved;
            $user     = $order->user;
            $wallet   = $user?->wallet;

            $balance  = $wallet ? (float) $wallet->balance : null;
            $canDebit = $wallet && $balance !== null && $balance >= $reserved;

            $action = $canDebit ? 'DEBIT' : 'ZERO_OUT';

            $this->line(sprintf(
                '  Order #%d | user_id=%s | reserved=R%.2f | wallet_balance=%s | action=%s',
                $order->id,
                $user?->id ?? '—',
                $reserved,
                $balance !== null ? 'R' . number_format($balance, 2) : 'no_wallet',
                $action
            ));

            $report[] = [
                'order_id'       => $order->id,
                'user_id'        => $user?->id,
                'wallet_id'      => $wallet?->id,
                'wallet_reserved' => $reserved,
                'wallet_balance' => $balance,
                'action'         => $action,
            ];
        }

        $this->exportCsv($report);

        if ($isDryRun) {
            $this->info('Dry-run complete. No rows were modified.');
            return 0;
        }

        // ── Execute repairs ───────────────────────────────────────────────
        $debited   = 0;
        $zeroedOut = 0;
        $failed    = 0;

        foreach ($orders as $order) {
            $reserved = (float) $order->wallet_reserved;
            $user     = $order->user;
            $wallet   = $user?->wallet;
            $balance  = $wallet ? (float) $wallet->balance : null;
            $canDebit = $wallet && $balance !== null && $balance >= $reserved;

            try {
                if ($canDebit) {
                    DB::transaction(function () use ($order, $wallet, $reserved, $walletService) {
                        $walletService->debit(
                            $wallet,
                            $reserved,
                            'event_registration_wallet_payment',
                            $order->id,
                            [
                                'repaired_by'   => 'finance:repair-wallet-reservations',
                                'order_id'      => $order->id,
                                'note'          => 'Retroactive debit for missed wallet reservation',
                            ]
                        );

                        $order->update(['wallet_debited' => true]);
                    });

                    Log::info('WALLET REPAIR: debited', [
                        'order_id'  => $order->id,
                        'amount'    => $reserved,
                        'wallet_id' => $wallet->id,
                    ]);

                    activity('finance_repair')
                        ->performedOn($order)
                        ->withProperties([
                            'action'         => 'wallet_debit_repaired',
                            'amount'         => $reserved,
                            'wallet_id'      => $wallet->id,
                            'order_id'       => $order->id,
                            'repaired_by'    => 'finance:repair-wallet-reservations',
                        ])
                        ->log("Wallet reservation repaired: R{$reserved} debited from wallet #{$wallet->id}");

                    $debited++;
                } else {
                    // Phantom reservation — cannot debit; zero it out with audit trail
                    DB::transaction(function () use ($order, $reserved) {
                        $order->update(['wallet_reserved' => 0]);
                    });

                    Log::warning('WALLET REPAIR: zeroed phantom reservation', [
                        'order_id'   => $order->id,
                        'was_amount' => $reserved,
                        'reason'     => $order->user ? 'insufficient_balance' : 'no_wallet',
                    ]);

                    activity('finance_repair')
                        ->performedOn($order)
                        ->withProperties([
                            'action'      => 'phantom_reservation_zeroed',
                            'was_amount'  => $reserved,
                            'order_id'    => $order->id,
                            'reason'      => $order->user ? 'insufficient_balance' : 'no_wallet',
                            'repaired_by' => 'finance:repair-wallet-reservations',
                        ])
                        ->log("Phantom wallet reservation R{$reserved} zeroed out for order #{$order->id}");

                    $zeroedOut++;
                }
            } catch (DuplicateTransactionException $e) {
                // Already debited by another process — sync the flag
                $order->update(['wallet_debited' => true]);
                $this->warn("  Order #{$order->id}: duplicate wallet transaction detected — synced wallet_debited=1");
                $debited++;
            } catch (\Throwable $e) {
                Log::error('WALLET REPAIR FAILED', [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);
                $this->error("  Order #{$order->id}: FAILED — {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("✅ Repairs complete: {$debited} debited, {$zeroedOut} zeroed out, {$failed} failed.");
        return $failed > 0 ? 1 : 0;
    }

    private function exportCsv(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $filename = 'finance/wallet_repair_' . now()->format('Ymd_His') . '.csv';
        $headers  = array_keys($rows[0]);
        $lines    = [implode(',', $headers)];

        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(
                fn($v) => '"' . str_replace('"', '""', (string) ($v ?? '')), $row
            ));
        }

        Storage::put($filename, implode("\n", $lines));
        $this->info("CSV exported to storage://{$filename}");
    }
}
