<?php

namespace App\Services;

use App\Models\CategoryEventRegistration;
use App\Models\CategoryAdjustment;
use App\Models\Player;
use App\Models\ClothingOrder;
use App\Models\ClothingRefund;
use App\Models\RegistrationManualReceipt;
use App\Models\RegistrationOrder;
use App\Models\TeamPaymentOrder;
use App\Models\Transaction;
use App\Models\TrialParticipationReceipt;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Read-only payer statement. Tender amounts are never inferred from reservations or current fees. */
class AccountStatementService
{
    private const LIMIT = 2000;

    public function build(User $user, string $from, string $to, string $player = '', string $category = 'all', bool $allTime = false): array
    {
        $rows = collect();
        $warnings = [];
        $hasPaymentStatus = Schema::hasColumn('transactions_pf', 'payment_status');
        if (! $hasPaymentStatus) {
            $warnings[] = 'Some older PayFast receipts cannot be verified against a paid checkout and may be missing from this statement. Contact Cape Tennis if you need help reconciling older payments.';
        }
        $start = ($allTime ? '1000-01-01' : $from).' 00:00:00';
        $end = ($allTime ? '9999-12-31' : $to).' 23:59:59';
        $receipts = $this->bounded(Transaction::query()->with(['player', 'event', 'category_event.event'])
            ->where('custom_int4', $user->id)
            ->when($hasPaymentStatus, fn ($q) => $q->where(fn ($status) => $status->where('payment_status', 'COMPLETE')->orWhereNull('payment_status')))
            ->where('is_test', false)
            ->whereNull('archived_at')->whereNotNull('pf_payment_id')->where('pf_payment_id', '!=', '')
            ->where('amount_gross', '>', 0)->whereBetween('created_at', [$start, $end]));

        foreach ($receipts->unique('pf_payment_id') as $receipt) {
            if ($receipt->custom_str5 === 'Order' && $receipt->transaction_type === 'Registration') {
                if ($this->legacyMetadataMatches($receipt)) {
                    $rows->push($this->row($receipt->created_at, 'Historical PayFast', 'PF '.$receipt->pf_payment_id,
                        'Legacy receipt '.$receipt->id, $this->legacyDetails($user, $receipt), $this->cents($receipt->amount_gross), 0));
                }
                continue;
            }
            if ($receipt->custom_str5 === 'CategoryAdjustment') {
                $adjustment = CategoryAdjustment::with('event')->whereKey($receipt->custom_int5)->where('user_id', $user->id)
                    ->where('status', 'completed')->whereNotNull('applied_at')->where('pay_status', true)->first();
                if ($adjustment && (int) $adjustment->event_id === (int) $receipt->event_id
                    && (int) $adjustment->player_id === (int) $receipt->player_id
                    && (string) $adjustment->payfast_pf_payment_id === (string) $receipt->pf_payment_id
                    && $this->cents($adjustment->difference) === $this->cents($receipt->amount_gross)) {
                    $rows->push($this->row($receipt->created_at, 'Category change / PayFast', 'PF '.$receipt->pf_payment_id,
                        'A'.$adjustment->id, [$this->detail($receipt->player, $adjustment->event, $adjustment->difference)], $this->cents($receipt->amount_gross), 0));
                }
                continue;
            }
            $team = $receipt->custom_str5 === 'TeamOrder';
            if (! $team && filled($receipt->custom_str5)) {
                continue;
            }
            $order = $receipt->custom_int5 ? $this->order($user, $team, (int) $receipt->custom_int5) : null;
            if ($receipt->custom_int5 && (! $order || ! $this->matchesEvent($order, (int) $receipt->event_id))) {
                continue;
            }
            if ($order) {
                if ((string) $order->payfast_pf_payment_id !== (string) $receipt->pf_payment_id
                    || $this->cents($order->payfast_amount_due) !== $this->cents($receipt->amount_gross)) {
                    continue;
                }
                $details = $this->details($order);
            } elseif ($hasPaymentStatus && $receipt->payment_status === 'COMPLETE' && ! $team && $receipt->player && $receipt->category_event
                && (int) $receipt->category_event->event_id === (int) $receipt->event_id) {
                $details = $this->legacyDetails($user, $receipt);
            } else {
                continue;
            }
            $rows->push($this->row($receipt->created_at, 'PayFast', 'PF '.$receipt->pf_payment_id,
                $order ? ($team ? 'T' : 'R').$order->id : 'Legacy receipt', $details,
                $this->cents($receipt->amount_gross), 0));
        }

        $debits = $this->bounded(WalletTransaction::query()->whereHas('wallet', fn ($q) => $q
            ->where('payable_type', $user->getMorphClass())->where('payable_id', $user->id))
            ->where('type', 'debit')->whereIn('source_type', ['event_registration_wallet_payment', 'team_registration_wallet_payment'])
            ->whereBetween('created_at', [$start, $end]));
        foreach ($debits as $debit) {
            $team = $debit->source_type === 'team_registration_wallet_payment';
            $order = $this->order($user, $team, (int) $debit->source_id);
            if ($order && $order->wallet_debited) {
                $rows->push($this->row($debit->created_at, 'Wallet', 'W'.$debit->id,
                    ($team ? 'T' : 'R').$order->id, $this->details($order), abs($this->cents($debit->amount)), 0));
            }
        }
        $credits = $this->bounded(WalletTransaction::query()->whereHas('wallet', fn ($q) => $q
            ->where('payable_type', $user->getMorphClass())->where('payable_id', $user->id))
            ->where('type', 'credit')->where('source_type', 'category_adjustment')->whereBetween('created_at', [$start, $end]));
        foreach ($credits as $credit) {
            $adjustment = CategoryAdjustment::with('event')->whereKey($credit->source_id)->where('user_id', $user->id)
                ->where('status', 'completed')->whereNotNull('applied_at')->where('wallet_transaction_id', $credit->id)->first();
            if ($adjustment && $this->cents($adjustment->difference) < 0 && abs($this->cents($adjustment->difference)) === $this->cents($credit->amount)) {
                $rows->push($this->row($credit->created_at, 'Category change / Wallet credit', 'W'.$credit->id,
                    'A'.$adjustment->id, [$this->detail(Player::find($adjustment->player_id), $adjustment->event, null)], 0, $this->cents($credit->amount)));
            }
        }

        foreach ([RegistrationManualReceipt::class, TrialParticipationReceipt::class] as $class) {
            if (! Schema::hasTable((new $class)->getTable())) {
                if ($category !== 'clothing') {
                    $warnings[] = 'Verified manual payment history is currently unavailable. This statement may be incomplete; contact Cape Tennis for assistance.';
                }
                continue;
            }
            $team = $class === TrialParticipationReceipt::class;
            $manual = $this->bounded($class::query()->whereHas('order', fn ($q) => $q->where('user_id', $user->id))
                ->whereBetween('paid_at', [$start, $end]));
            foreach ($manual as $receipt) {
                $order = $this->order($user, $team, (int) $receipt->order_id);
                if ($order && $this->matchesEvent($order, (int) $receipt->event_id)) {
                    $rows->push($this->row($receipt->paid_at, ucfirst($receipt->method ?? 'Manual'),
                        'Receipt '.$receipt->id.($receipt->reference ? ' / '.$receipt->reference : ''),
                        ($team ? 'T' : 'R').$order->id, $this->details($order), $this->cents($receipt->amount), 0));
                }
            }
        }

        $refunds = $this->bounded(TeamPaymentOrder::query()->with(['player', 'event'])
            ->where('user_id', $user->id)->where('refund_status', 'completed')->whereBetween('refunded_at', [$start, $end]));
        foreach ($refunds as $refund) {
            $rows->push($this->row($refund->refunded_at, 'Refund: '.($refund->refund_method ?: 'recorded'),
                'Team refund '.$refund->id, 'T'.$refund->id, $this->details($refund), 0, $this->cents($refund->refund_net)));
        }
        $entryRefunds = $this->bounded(CategoryEventRegistration::withTrashed()->with(['players', 'categoryEvent.event'])
            ->where('user_id', $user->id)->where('refund_status', 'completed')->whereBetween('refunded_at', [$start, $end]));
        foreach ($entryRefunds as $refund) {
            $details = $refund->players->map(fn ($p) => $this->detail($p, $refund->categoryEvent?->event, null))->all();
            $rows->push($this->row($refund->refunded_at, 'Refund: '.($refund->refund_method ?: 'recorded'),
                'Entry refund '.$refund->id, 'Entry '.$refund->id, $details, 0, $this->cents($refund->refund_net)));
        }

        $clothing = $this->bounded(ClothingOrder::query()->with(['player', 'event', 'items.itemType', 'transaction'])
            ->where('user_id', $user->id)->where('pay_status', 1)
            ->where(fn ($q) => $q->whereBetween('paid_at', [$start, $end])->orWhereHas('transaction', fn ($t) => $t->whereBetween('created_at', [$start, $end]))));
        foreach ($clothing as $order) {
            $tx = $order->transaction;
            $date = $order->paid_at;
            $amount = $order->amount_paid;
            $reference = $order->payfast_pf_payment_id ?: $order->pf_id;
            if (! $date && $hasPaymentStatus && $tx && ! $tx->is_test && ! $tx->archived_at && $tx->payment_status === 'COMPLETE'
                && (int) $tx->custom_int4 === (int) $user->id && (int) $tx->event_id === (int) $order->event_id) {
                $date = $tx->created_at;
                $amount = $tx->amount_gross;
            }
            if ($date && $date->betweenIncluded($start, $end) && $reference && $this->cents($amount) > 0) {
                $rows->push($this->row($date, 'Clothing / PayFast', 'PF '.$reference, 'C'.$order->id,
                    $this->clothingDetails($order), $this->cents($amount), 0));
            }
        }
        $clothingRefunds = $this->bounded(ClothingRefund::query()->with(['order.player', 'order.event', 'order.items.itemType'])
            ->where('payer_id', $user->id)->whereHas('order', fn ($q) => $q->where('user_id', $user->id))
            ->where('refund_status', 'completed')->whereBetween('refunded_at', [$start, $end]));
        foreach ($clothingRefunds as $refund) {
            if ((int) $refund->event_id === (int) $refund->order->event_id) {
                $rows->push($this->row($refund->refunded_at, 'Clothing refund / '.($refund->refund_method ?: 'recorded'),
                    'Clothing refund '.$refund->id, 'C'.$refund->clothing_order_id, $this->clothingDetails($refund->order), 0, $this->cents($refund->refund_net)));
            }
        }

        $rows = $rows->groupBy(fn ($r) => $r['paid'] > 0 && str_starts_with($r['reference'], 'PF ') ? $r['reference'] : $r['checkout'].'|'.$r['reference'].'|'.$r['method'])
            ->map(function ($group) {
                $row = $group->first();
                if ($group->count() === 1) return $row;
                $row['details'] = $group->flatMap(fn ($r) => $r['details'])->unique(fn ($d) => $d['player'].'|'.$d['event'].'|'.($d['fee'] ?? 'unknown'))->values()->all();

                return $row;
            })->values();
        if ($category !== 'all') {
            $rows = $rows->filter(fn ($r) => ($category === 'clothing') === str_starts_with($r['checkout'], 'C'));
        }
        if ($player !== '') {
            $rows = $rows->filter(fn ($r) => collect($r['details'])->contains(fn ($d) => mb_stripos($d['player'], $player) !== false));
        }
        if ($rows->count() > self::LIMIT) {
            $this->tooMany();
        }
        $rows = $rows->sortBy(fn ($r) => $r['date'].' '.$r['reference'])->values();
        $historicalRows = $this->historicalRecords($user, $rows, $player, $category, $start, $end);
        $recordedFeeTotal = $historicalRows->flatMap(fn ($row) => $row['details'])->filter(fn ($detail) => isset($detail['recorded_fee_item_id']))
            ->unique('recorded_fee_item_id')->sum('recorded_fee');

        return ['rows' => $rows, 'paid' => $rows->sum('paid'), 'refunded' => $rows->sum('refunded'),
            'net' => $rows->sum('paid') - $rows->sum('refunded'), 'from' => $from, 'to' => $to,
            'player' => $player, 'category' => $category, 'allTime' => $allTime, 'historicalRows' => $historicalRows,
            'recordedFeeTotal' => $recordedFeeTotal,
            'warnings' => array_values(array_unique($warnings)), 'generated_at' => now(), 'account' => $user];
    }

    private function legacyMetadataMatches(Transaction $receipt): bool
    {
        $player = Player::find($receipt->custom_int2);
        if (! $player || ! $receipt->event || ! $receipt->created_at
            || ($receipt->player_id && (int) $receipt->custom_int2 !== (int) $receipt->player_id)
            || (int) $receipt->custom_int3 !== (int) $receipt->event_id) {
            return false;
        }
        $receipt->setRelation('player', $player);
        return \App\Models\Team::whereKey($receipt->custom_int1)
            ->whereHas('category', fn ($q) => $q->where('event_id', $receipt->event_id))->exists()
            && \App\Models\TeamPlayer::where('team_id', $receipt->custom_int1)->where('player_id', $player->id)->where('pay_status', 1)->exists();
    }

    private function legacyDetails(User $user, Transaction $receipt): array
    {
        $entries = CategoryEventRegistration::withTrashed()->with(['players', 'categoryEvent.event'])->where('user_id', $user->id)
            ->where('payment_status_id', 1)->where('pf_transaction_id', $receipt->pf_payment_id)
            ->whereHas('categoryEvent', fn ($q) => $q->where('event_id', $receipt->event_id))->limit(self::LIMIT + 1)->get();
        if ($entries->count() > self::LIMIT) $this->tooMany();
        $details = $entries->flatMap(fn ($entry) => $entry->players->map(fn ($p) => $this->detail($p, $entry->categoryEvent?->event, null)));

        return $details->isNotEmpty() ? $details->unique(fn ($d) => $d['player'].'|'.$d['event'])->values()->all()
            : [$this->detail($receipt->player, $receipt->event, null)];
    }

    private function historicalRecords(User $user, Collection $knownRows, string $player, string $category, string $start, string $end): Collection
    {
        $history = collect();
        $knownReferences = $knownRows->pluck('reference');
        if ($category !== 'clothing') {
            $teamOrders = $this->bounded(TeamPaymentOrder::with(['player', 'event'])->where('user_id', $user->id)->where('pay_status', true)
                ->where(fn ($q) => $q->whereNull('collection_status')->orWhere('collection_status', '!=', 'reconciliation_required')));
            foreach ($teamOrders as $order) {
                if ($knownRows->contains('checkout', 'T'.$order->id) || $this->hasDatedTeamReceipt($user, $order)) continue;
                $history->push(['checkout' => 'Historical team entry T'.$order->id,
                    'reference' => $order->payfast_pf_payment_id ?: 'Reference unavailable', 'method' => 'Recorded as paid',
                    'date' => null, 'amount' => null, 'details' => [$this->detail($order->player, $order->event, null)]]);
            }
            $entries = $this->bounded(CategoryEventRegistration::withTrashed()->with(['players', 'categoryEvent.event'])
                ->where('user_id', $user->id)->where('payment_status_id', 1));
            foreach ($entries as $entry) {
                $item = \App\Models\RegistrationOrderItems::where('registration_id', $entry->registration_id)
                    ->where('category_event_id', $entry->category_event_id)->whereHas('order', fn ($q) => $q->where('user_id', $user->id)->where('pay_status', true))->first();
                if ($item && $knownRows->contains('checkout', 'R'.$item->order_id)) {
                    continue;
                }
                if ($entry->pf_transaction_id && $knownReferences->contains('PF '.$entry->pf_transaction_id)) {
                    continue;
                }
                if ($this->hasDatedEntryReceipt($user, $entry)) {
                    continue;
                }
                // Known dated evidence outside the selected period is not an undated historical record.
                if ($item?->order?->wallet_transaction_id && WalletTransaction::whereKey($item->order->wallet_transaction_id)
                    ->where('type', 'debit')->where('source_type', 'event_registration_wallet_payment')->where('source_id', $item->order_id)
                    ->where('amount', '>', 0)->whereNotNull('created_at')->whereHas('wallet', fn ($q) => $q
                        ->where('payable_type', $user->getMorphClass())->where('payable_id', $user->id))->exists()) {
                    continue;
                }
                $details = $entry->players->map(function ($p) use ($entry, $user) {
                    $detail = $this->detail($p, $entry->categoryEvent?->event, null);
                    $fee = $this->historicalEntryFee($user, $entry, (int) $p->id);
                    $detail['recorded_fee'] = $fee['amount'] ?? null;
                    $detail['recorded_fee_item_id'] = $fee['item_id'] ?? null;

                    return $detail;
                })->all();
                if ($details === []) $details = [$this->detail(null, $entry->categoryEvent?->event, null)];
                $history->push(['checkout' => 'Historical entry '.$entry->id, 'reference' => $entry->pf_transaction_id ?: 'Reference unavailable',
                    'method' => $entry->payment_method ?: 'Recorded as paid', 'date' => null, 'amount' => null, 'details' => $details]);
            }
        }
        if ($category !== 'entry') {
            $clothing = $this->bounded(ClothingOrder::with(['player', 'event', 'items.itemType', 'transaction'])->where('user_id', $user->id)->where('pay_status', 1));
            foreach ($clothing as $order) {
                if ($order->paid_at && ! $order->paid_at->betweenIncluded($start, $end)) {
                    continue;
                }
                if ($knownRows->contains('checkout', 'C'.$order->id)) {
                    continue;
                }
                if ($order->paid_at && $this->cents($order->amount_paid) > 0 && ($order->payfast_pf_payment_id ?: $order->pf_id)) {
                    continue;
                }
                $receipt = $order->transaction;
                if (! $order->paid_at && $receipt && Schema::hasColumn('transactions_pf', 'payment_status') && $receipt->payment_status === 'COMPLETE'
                    && ! $receipt->is_test && ! $receipt->archived_at && $receipt->created_at && $this->cents($receipt->amount_gross) > 0
                    && (int) $receipt->custom_int4 === (int) $user->id && (int) $receipt->event_id === (int) $order->event_id) {
                    continue;
                }
                $history->push(['checkout' => 'Historical clothing C'.$order->id, 'reference' => $order->payfast_pf_payment_id ?: $order->pf_id ?: 'Reference unavailable',
                    'method' => $order->payment_method ?: 'Recorded as paid', 'date' => $order->paid_at?->format('Y-m-d H:i:s'),
                    'amount' => $order->amount_paid !== null && $this->cents($order->amount_paid) > 0 ? $this->cents($order->amount_paid) : null,
                    'details' => $this->clothingDetails($order)]);
            }
        }
        if ($player !== '') {
            $history = $history->filter(fn ($r) => collect($r['details'])->contains(fn ($d) => mb_stripos($d['player'], $player) !== false));
            $history = $history->map(function ($row) use ($player) {
                $row['details'] = array_values(array_filter($row['details'], fn ($detail) => mb_stripos($detail['player'], $player) !== false));

                return $row;
            });
        }
        $history = $history->map(function ($row) {
            $fees = collect($row['details'])->filter(fn ($detail) => isset($detail['recorded_fee_item_id']))->unique('recorded_fee_item_id');
            $row['recordedFee'] = $fees->isEmpty() ? null : $fees->sum('recorded_fee');
            $row['partialRecordedFee'] = $fees->isNotEmpty() && $fees->count() < count($row['details']);

            return $row;
        });
        if ($history->count() > self::LIMIT) $this->tooMany();

        return $history->values();
    }

    private function historicalEntryFee(User $user, CategoryEventRegistration $entry, int $playerId): ?array
    {
        if (! Schema::hasColumn('registration_order_items', 'user_id')) return null;
        $items = \App\Models\RegistrationOrderItems::where('user_id', $user->id)
            ->where('registration_id', $entry->registration_id)->where('category_event_id', $entry->category_event_id)
            ->where('player_id', $playerId)->limit(2)->get(['id', 'item_price']);
        if ($items->count() !== 1 || $items->first()->item_price === null) return null;
        $fee = $this->cents($items->first()->item_price);

        return $fee > 0 ? ['amount' => $fee, 'item_id' => (int) $items->first()->id] : null;
    }

    private function hasDatedEntryReceipt(User $user, CategoryEventRegistration $entry): bool
    {
        if (! $entry->pf_transaction_id) return false;
        $receipts = Transaction::with(['player', 'event', 'category_event'])->where('custom_int4', $user->id)
            ->where('pf_payment_id', $entry->pf_transaction_id)->where('event_id', $entry->categoryEvent?->event_id)
            ->where('is_test', false)->whereNull('archived_at')->whereNotNull('created_at')->where('amount_gross', '>', 0)->limit(2)->get();
        $hasStatus = Schema::hasColumn('transactions_pf', 'payment_status');
        foreach ($receipts as $receipt) {
            if ($hasStatus && filled($receipt->payment_status) && $receipt->payment_status !== 'COMPLETE') continue;
            if ($receipt->custom_str5 === 'Order' && $receipt->transaction_type === 'Registration' && $this->legacyMetadataMatches($receipt)) return true;
            if (blank($receipt->custom_str5) && ! $receipt->custom_int5 && $hasStatus && $receipt->payment_status === 'COMPLETE'
                && (int) $receipt->category_event?->event_id === (int) $receipt->event_id) return true;
            if (blank($receipt->custom_str5) && $receipt->custom_int5) {
                $order = $this->order($user, false, (int) $receipt->custom_int5);
                if ($order && $this->matchesEvent($order, (int) $receipt->event_id)
                    && (string) $order->payfast_pf_payment_id === (string) $receipt->pf_payment_id
                    && $this->cents($order->payfast_amount_due) === $this->cents($receipt->amount_gross)) return true;
            }
            if ($receipt->custom_str5 === 'TeamOrder' && $receipt->custom_int5) {
                $order = $this->order($user, true, (int) $receipt->custom_int5);
                if ($order && $this->matchesEvent($order, (int) $receipt->event_id)
                    && (string) $order->payfast_pf_payment_id === (string) $receipt->pf_payment_id
                    && $this->cents($order->payfast_amount_due) === $this->cents($receipt->amount_gross)) return true;
            }
        }

        return false;
    }

    private function hasDatedTeamReceipt(User $user, TeamPaymentOrder $order): bool
    {
        $receipt = Transaction::where('custom_int4', $user->id)->where('custom_str5', 'TeamOrder')->where('custom_int5', $order->id)
            ->where('event_id', $order->event_id)->where('pf_payment_id', $order->payfast_pf_payment_id)
            ->where('amount_gross', $order->payfast_amount_due)->where('amount_gross', '>', 0)->whereNotNull('created_at')
            ->where('is_test', false)->whereNull('archived_at')
            ->when(Schema::hasColumn('transactions_pf', 'payment_status'), fn ($q) => $q->where(fn ($s) => $s->where('payment_status', 'COMPLETE')->orWhereNull('payment_status')))->exists();
        if ($receipt) return true;
        if ($order->wallet_debited && WalletTransaction::where('source_type', 'team_registration_wallet_payment')->where('source_id', $order->id)
            ->where('type', 'debit')->where('amount', '>', 0)->whereNotNull('created_at')->whereHas('wallet', fn ($q) => $q
                ->where('payable_type', $user->getMorphClass())->where('payable_id', $user->id))->exists()) return true;

        return Schema::hasTable('trial_participation_receipts') && TrialParticipationReceipt::where('order_id', $order->id)
            ->where('event_id', $order->event_id)->where('amount', '>', 0)->whereNotNull('paid_at')->exists();
    }

    private function order(User $user, bool $team, int $id): RegistrationOrder|TeamPaymentOrder|null
    {
        $query = $team ? TeamPaymentOrder::with(['player', 'event']) : RegistrationOrder::with(['items.player', 'items.category_event.event']);
        $query->whereKey($id)->where('user_id', $user->id)->where('pay_status', true);
        if ($team) {
            $query->where(fn ($q) => $q->whereNull('collection_status')->orWhere('collection_status', '!=', 'reconciliation_required'));
        }

        return $query->first();
    }

    private function matchesEvent(RegistrationOrder|TeamPaymentOrder $order, int $eventId): bool
    {
        return $order instanceof TeamPaymentOrder ? (int) $order->event_id === $eventId
            : $order->items->isNotEmpty() && $order->items->every(fn ($i) => $i->category_event && (int) $i->category_event->event_id === $eventId);
    }

    private function details(RegistrationOrder|TeamPaymentOrder $order): array
    {
        return $order instanceof TeamPaymentOrder
            ? [$this->detail($order->player, $order->event, $order->total_amount)]
            : $order->items->map(fn ($i) => $this->detail($i->player, $i->category_event?->event, $i->item_price))->all();
    }

    private function clothingDetails(ClothingOrder $order): array
    {
        $details = $this->detail($order->player, $order->event, $order->total);
        $details['event'] .= ' — Clothing: '.$order->items->map(fn ($i) => ($i->item_name ?: $i->itemType?->item_type_name ?: 'Item').' × '.$i->qty)->implode(', ');

        return [$details];
    }

    private function detail($player, $event, $fee): array
    {
        return ['player' => trim(($player?->name ?? '').' '.($player?->surname ?? '')) ?: 'Historical player unavailable',
            'event' => $event?->name ?? 'Historical event unavailable', 'event_date' => $event?->start_date,
            'fee' => $fee === null ? null : $this->cents($fee)];
    }

    private function row($date, string $method, string $reference, string $checkout, array $details, int $paid, int $refunded): array
    {
        return ['date' => $date->format('Y-m-d H:i:s'), 'method' => $method, 'reference' => $reference,
            'checkout' => $checkout, 'details' => $details, 'paid' => $paid, 'refunded' => $refunded];
    }

    private function cents($amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function bounded($query): Collection
    {
        $records = $query->limit(self::LIMIT + 1)->get();
        if ($records->count() > self::LIMIT) {
            $this->tooMany();
        }

        return $records;
    }

    private function tooMany(): never
    {
        throw ValidationException::withMessages(['from' => 'This period contains too many records. Choose a shorter date range.']);
    }
}
