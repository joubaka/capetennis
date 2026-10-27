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
use Illuminate\Validation\ValidationException;

/** Read-only payer statement. Tender amounts are never inferred from reservations or current fees. */
class AccountStatementService
{
    private const LIMIT = 2000;

    public function build(User $user, string $from, string $to, string $player = '', string $category = 'all'): array
    {
        $rows = collect();
        $start = $from.' 00:00:00';
        $end = $to.' 23:59:59';
        $receipts = $this->bounded(Transaction::query()->with(['player', 'event', 'category_event.event'])
            ->where('custom_int4', $user->id)->where(fn ($q) => $q->where('payment_status', 'COMPLETE')->orWhereNull('payment_status'))->where('is_test', false)
            ->whereNull('archived_at')->whereNotNull('pf_payment_id')->where('pf_payment_id', '!=', '')
            ->where('amount_gross', '>', 0)->whereBetween('created_at', [$start, $end]));

        foreach ($receipts->unique('pf_payment_id') as $receipt) {
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
            } elseif ($receipt->payment_status === 'COMPLETE' && ! $team && $receipt->player && $receipt->category_event
                && (int) $receipt->category_event->event_id === (int) $receipt->event_id) {
                $details = [$this->detail($receipt->player, $receipt->category_event->event, null)];
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
            if (! $date && $tx && ! $tx->is_test && ! $tx->archived_at && $tx->payment_status === 'COMPLETE'
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

        return ['rows' => $rows, 'paid' => $rows->sum('paid'), 'refunded' => $rows->sum('refunded'),
            'net' => $rows->sum('paid') - $rows->sum('refunded'), 'from' => $from, 'to' => $to,
            'player' => $player, 'category' => $category, 'generated_at' => now(), 'account' => $user];
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
