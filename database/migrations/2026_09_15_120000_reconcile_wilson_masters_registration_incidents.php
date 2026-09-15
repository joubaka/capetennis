<?php

use App\Domain\Entries\Services\EntryService;
use App\Domain\Payments\Services\RegistrationPaymentService;
use App\Domain\Refunds\Services\RefundExecutionService;
use App\Models\CategoryEventRegistration;
use App\Models\RegistrationOrder;
use App\Models\User;
use App\Services\Masters\MastersInvitationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $requiredTables = [
            'users', 'players', 'events', 'category_events', 'registrations',
            'player_registrations', 'category_event_registrations',
            'registration_orders', 'registration_order_items',
            'wallet_transactions', 'transactions_pf', 'masters_invitations',
            'category_results',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::hasTable($table)) {
                return;
            }
        }

        DB::transaction(function (): void {
            $wilsonEvent = DB::table('events')->where('id', 254)->first(['id', 'name']);
            if (! $wilsonEvent) {
                return;
            }
            if (stripos((string) $wilsonEvent->name, 'Wilson') === false
                || stripos((string) $wilsonEvent->name, 'Masters') === false) {
                throw new RuntimeException('Event 254 is not the expected Wilson Masters event.');
            }

            /** @var User|null $actor */
            $actor = User::query()->find(584);
            if (! $actor) {
                throw new RuntimeException('Wilson correction actor 584 was not found.');
            }

            $this->assertPlayerRegistration(3419, 20925, 'Emma Van Rooyen');
            $this->assertPlayerRegistration(3419, 20926, 'Emma Van Rooyen');
            $this->assertPlayerRegistration(3419, 20927, 'Emma Van Rooyen');
            $this->assertCategoryEntry(19142, 20925, 2164, 254, 1, '325326961');
            $this->assertCategoryEntry(19143, 20926, 2164, 254, 0, null);
            $this->assertCategoryEntry(19144, 20927, 2164, 254, 1, '325328493');
            $this->assertOrder(10435, 20925, true, '325326961');
            $this->assertOrder(10436, 20926, false, null);
            $this->assertOrder(10437, 20927, true, '325328493');
            $this->assertPayfastTransaction('325326961', 10435, 3419, 2164, 254, 285.00);
            $this->assertPayfastTransaction('325328493', 10437, 3419, 2164, 254, 285.00);

            $canonicalEmmaInvitation = DB::table('masters_invitations')
                ->where('registration_id', 20927)
                ->where('order_id', 10437)
                ->where('status', 'paid_confirmed')
                ->lockForUpdate()
                ->first();
            if (! $canonicalEmmaInvitation) {
                throw new RuntimeException('Emma canonical paid Masters invitation no longer matches the audited state.');
            }

            $emmaDuplicate = CategoryEventRegistration::query()->lockForUpdate()->findOrFail(19142);
            if ($emmaDuplicate->status !== 'withdrawn') {
                app(EntryService::class)->withdrawEntryAsAdmin($emmaDuplicate, $actor);
            }
            app(RefundExecutionService::class)->recordExternalPayfastRefund(
                $emmaDuplicate->fresh(),
                '325326961',
                285.00,
                '2026-09-09 13:38:08',
                $actor,
                [
                    'source' => 'PayFast transaction history 11307280',
                    'processor_fee_reversal' => 12.79,
                    'processor_net_reversal' => 272.21,
                    'note' => 'Full R285 customer refund confirmed externally; original PayFast transaction retained.',
                ]
            );

            $emmaDraftOrder = RegistrationOrder::query()->lockForUpdate()->findOrFail(10436);
            if ($emmaDraftOrder->status !== 'cancelled') {
                app(RegistrationPaymentService::class)->cancelPayment($emmaDraftOrder);
            }
            $emmaDraftEntry = CategoryEventRegistration::query()->lockForUpdate()->findOrFail(19143);
            if ($emmaDraftEntry->status !== 'withdrawn') {
                app(EntryService::class)->withdrawEntryAsAdmin($emmaDraftEntry, $actor);
            }

            $this->assertPlayerRegistration(2118, 21210, 'Sune Viljoen');
            $this->assertCategoryEntry(19423, 21210, 2168, 254, 1, null);
            $this->assertOrder(10683, 21210, true, null, 'wallet', 39);
            $walletTransaction = DB::table('wallet_transactions')->where('id', 39)->lockForUpdate()->first();
            if (! $walletTransaction
                || $walletTransaction->type !== 'debit'
                || round((float) $walletTransaction->amount, 2) !== 285.00
                || (int) $walletTransaction->source_id !== 10683
                || $walletTransaction->source_type !== 'event_registration_wallet_payment') {
                throw new RuntimeException('Sune wallet transaction 39 no longer matches the audited payment evidence.');
            }
            app(RegistrationPaymentService::class)->markOrderRegistrationsPaid(
                RegistrationOrder::query()->findOrFail(10683),
                null,
                (int) RegistrationOrder::query()->findOrFail(10683)->user_id
            );

            $this->assertPlayerRegistration(200, 21013, 'Juan Sales');
            $this->assertCategoryEntry(19230, 21013, 2180, 254, 1, '326042075', 'withdrawn');
            $this->assertOrder(10516, 21013, true, '326042075');
            $this->assertPayfastTransaction('326042075', 10516, 200, 2180, 254, 285.00);
            $juanInvitation = DB::table('masters_invitations')
                ->where('id', 797)
                ->where('registration_id', 21013)
                ->where('status', 'paid_confirmed')
                ->lockForUpdate()
                ->first();
            if ($juanInvitation) {
                app(MastersInvitationService::class)->handlePaidWithdrawal(21013, $actor, false);
            } elseif (! DB::table('masters_invitations')->where('id', 797)->where('status', 'withdrawn')->exists()) {
                throw new RuntimeException('Juan Masters invitation 797 no longer matches an idempotent correction state.');
            }

            $this->assertPlayerRegistration(200, 21207, 'Juan Sales');
            $this->assertCategoryEntry(19422, 21207, 2104, 245, 1, null, 'active');
            if (! DB::table('category_results')
                ->where('id', 6355)
                ->where('event_id', 245)
                ->where('registration_id', 21207)
                ->where('position', 1)
                ->exists()) {
                throw new RuntimeException('Juan Saturday trials winner result is not intact; no Wilson corrections were applied.');
            }
        });
    }

    private function assertPlayerRegistration(int $playerId, int $registrationId, string $expectedName): void
    {
        $player = DB::table('player_registrations as pr')
            ->join('players as p', 'p.id', '=', 'pr.player_id')
            ->where('pr.player_id', $playerId)
            ->where('pr.registration_id', $registrationId)
            ->first(['p.name', 'p.surname']);
        $actualName = $player ? trim((string) $player->name.' '.(string) $player->surname) : '';
        if (! $player || strcasecmp($actualName, $expectedName) !== 0) {
            throw new RuntimeException("Registration {$registrationId} is not linked to {$expectedName}.");
        }
    }

    private function assertCategoryEntry(
        int $entryId,
        int $registrationId,
        int $categoryEventId,
        int $eventId,
        int $paid,
        ?string $pfPaymentId,
        ?string $expectedStatus = null
    ): void {
        $entry = DB::table('category_event_registrations as cer')
            ->join('category_events as ce', 'ce.id', '=', 'cer.category_event_id')
            ->where('cer.id', $entryId)
            ->where('cer.registration_id', $registrationId)
            ->where('cer.category_event_id', $categoryEventId)
            ->where('ce.event_id', $eventId)
            ->lockForUpdate()
            ->first(['cer.payment_status_id', 'cer.pf_transaction_id', 'cer.status']);

        if (! $entry
            || (int) $entry->payment_status_id !== $paid
            || ($pfPaymentId !== null && (string) $entry->pf_transaction_id !== $pfPaymentId)
            || ($expectedStatus !== null && $entry->status !== $expectedStatus)) {
            throw new RuntimeException("Entry {$entryId} no longer matches the audited Wilson state.");
        }
    }

    private function assertOrder(
        int $orderId,
        int $registrationId,
        bool $paid,
        ?string $pfPaymentId,
        ?string $paymentMethod = null,
        ?int $walletTransactionId = null
    ): void {
        $order = DB::table('registration_orders as ro')
            ->join('registration_order_items as roi', 'roi.order_id', '=', 'ro.id')
            ->where('ro.id', $orderId)
            ->where('roi.registration_id', $registrationId)
            ->lockForUpdate()
            ->first([
                'ro.pay_status', 'ro.payfast_pf_payment_id', 'ro.payment_method',
                'ro.wallet_transaction_id', 'ro.total_fee', 'roi.item_price',
            ]);

        if (! $order
            || (bool) $order->pay_status !== $paid
            || ($pfPaymentId !== null && (string) $order->payfast_pf_payment_id !== $pfPaymentId)
            || ($paymentMethod !== null && $order->payment_method !== $paymentMethod)
            || ($walletTransactionId !== null && (int) $order->wallet_transaction_id !== $walletTransactionId)
            || round((float) $order->total_fee, 2) !== 285.00
            || round((float) $order->item_price, 2) !== 285.00) {
            throw new RuntimeException("Order {$orderId} no longer matches the audited Wilson payment state.");
        }
    }

    private function assertPayfastTransaction(
        string $pfPaymentId,
        int $orderId,
        int $playerId,
        int $categoryEventId,
        int $eventId,
        float $gross
    ): void {
        $transaction = DB::table('transactions_pf')
            ->where('pf_payment_id', $pfPaymentId)
            ->where('custom_int5', $orderId)
            ->where('player_id', $playerId)
            ->where('category_event_id', $categoryEventId)
            ->where('event_id', $eventId)
            ->lockForUpdate()
            ->first(['amount_gross']);

        if (! $transaction || round((float) $transaction->amount_gross, 2) !== round($gross, 2)) {
            throw new RuntimeException("PayFast transaction {$pfPaymentId} no longer matches the audited payment.");
        }
    }

    public function down(): void
    {
        // These are audited historical corrections. Rolling back must not
        // recreate a duplicate charge, stale invitation, or unpaid entry.
    }
};
