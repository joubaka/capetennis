<?php

namespace App\Domain\Payments\Services;

use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Player;
use App\Models\Transaction;
use App\Support\FinanceMutationScope;
use Illuminate\Support\Facades\DB;

class PaymentTransactionService
{
    public function record(array $data, mixed $order): ?Transaction
    {
        return FinanceMutationScope::run('payment_transaction_write', function () use ($data, $order) {
            return DB::transaction(function () use ($data, $order) {
                if ($order === 'admin') {
                    return $this->recordAdminTransaction($data);
                }

                if ($order === 'withdrawel_before_deadline') {
                    return $this->recordWithdrawalTransaction($data);
                }

                return $this->recordPayfastTransaction($data);
            });
        });
    }

    protected function recordAdminTransaction(array $data): ?Transaction
    {
        $categoryEvent = CategoryEvent::with('event', 'category')->find($data['categoryEvent'] ?? null);
        if (!$categoryEvent) {
            return null;
        }

        $transaction = new Transaction();
        $transaction->transaction_type = 'Registration';
        $transaction->amount_gross = 0;
        $transaction->amount_net = 0;
        $transaction->amount_fee = 0;
        $transaction->event_id = $categoryEvent->event->id;
        $transaction->item_name = $categoryEvent->event->name;
        $transaction->category_event_id = $categoryEvent->id;
        $transaction->player_id = $data['player_id'] ?? null;
        $transaction->custom_str1 = $categoryEvent->category?->name ?? 'Admin Remove';

        if (!empty($data['player_id'])) {
            $player = Player::find($data['player_id']);
            if ($player) {
                $transaction->custom_int2 = $player->id;
                $transaction->custom_str2 = trim($player->name . ' ' . $player->surname);
            }
        }

        $transaction->custom_int3 = $categoryEvent->event->id;
        $transaction->custom_str3 = $categoryEvent->event->name;

        if (auth()->check()) {
            $transaction->custom_int4 = auth()->id();
            $transaction->custom_str4 = auth()->user()->name;
        }

        $transaction->save();

        return $transaction;
    }

    protected function recordWithdrawalTransaction(array $data): ?Transaction
    {
        $registration = CategoryEventRegistration::with('categoryEvent.event', 'categoryEvent.category')
            ->find($data['categoryEventRegistration'] ?? null);

        if (!$registration) {
            return null;
        }

        $transaction = new Transaction();
        $transaction->transaction_type = 'Withdrawal';
        $transaction->category_event_id = $registration->category_event_id;
        $transaction->event_id = $registration->categoryEvent->event->id;
        $transaction->item_name = $registration->categoryEvent->event->name;
        $transaction->custom_int1 = $registration->category_event_id;
        $transaction->custom_str1 = $registration->categoryEvent->category?->name;

        if ($registration->payfast_id === 'Admin') {
            $transaction->amount_gross = 0;
            $transaction->amount_fee = 0;
            $transaction->amount_net = 10;
        } else {
            $entryFee = (float) $registration->categoryEvent->entry_fee;
            $payfastFee = \App\Models\SiteSetting::calculatePayfastFee($entryFee);

            $transaction->cape_tennis_fee = 10;
            $transaction->amount_gross = -$entryFee;
            $transaction->amount_fee = -$payfastFee;
            $transaction->amount_net = $entryFee - ($payfastFee - 10);
        }

        $player = $registration->registration->players->first();
        if ($player) {
            $transaction->custom_int2 = $player->player_id;
            $transaction->custom_str2 = trim($player->name . ' ' . $player->surname);
            $transaction->player_id = $player->player_id;
        }

        $transaction->custom_int3 = $registration->categoryEvent->event->id;
        $transaction->custom_str3 = $registration->categoryEvent->event->name;

        if (auth()->check()) {
            $transaction->custom_int4 = auth()->id();
            $transaction->custom_str4 = auth()->user()->name;
        }

        $transaction->save();

        return $transaction;
    }

    protected function recordPayfastTransaction(array $data): Transaction
    {
        $transaction = !empty($data['pf_payment_id'])
            ? Transaction::firstOrNew(['pf_payment_id' => $data['pf_payment_id']])
            : new Transaction();

        $transaction->transaction_type = 'Registration';
        $transaction->amount_gross = $data['amount_gross'] ?? null;

        $gross = (float) ($data['amount_gross'] ?? 0);
        $paymentMethod = $data['payment_method'] ?? null;
        $configuredFee = \App\Models\SiteSetting::calculatePayfastFee($gross, $paymentMethod);

        $transaction->amount_fee = $configuredFee;
        $transaction->amount_net = round($gross - $configuredFee, 2);
        $transaction->event_id = $data['custom_int3'] ?? null;
        $transaction->category_event_id = $data['custom_int1'] ?? null;
        $transaction->player_id = $data['custom_int2'] ?? null;

        foreach (['1', '2', '3', '4', '5'] as $i) {
            $intKey = "custom_int{$i}";
            $strKey = "custom_str{$i}";

            if (array_key_exists($intKey, $data)) {
                $transaction->{$intKey} = $data[$intKey];
            }

            if (array_key_exists($strKey, $data)) {
                $transaction->{$strKey} = $data[$strKey];
            }
        }

        if (!empty($data['pf_payment_id'])) {
            $transaction->pf_payment_id = $data['pf_payment_id'];
        }

        $transaction->item_name = $data['item_name'] ?? null;
        $transaction->email_address = $data['email_address'] ?? null;
        $transaction->save();

        return $transaction;
    }
}
