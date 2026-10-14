<?php

namespace App\Domain\Payments\Services;

use App\Models\{RegistrationOrder, TeamPaymentOrder};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class UnpaidCheckoutExpiryService
{
    public function expire(Model $order): bool
    {
        return match (true) {
            $order instanceof RegistrationOrder => app(RegistrationPaymentService::class)->expireUnpaidCheckout($order),
            $order instanceof TeamPaymentOrder => app(TeamPaymentService::class)->expireUnpaidCheckout($order),
            default => false,
        };
    }

    public function assertAvailable(Model $order): void
    {
        $expired = $this->expire($order);
        $current = $order->fresh();
        $proofModel = $order instanceof RegistrationOrder ? \App\Models\TrialPaymentProof::class : \App\Models\TrialParticipationProof::class;
        $pendingProof = $current && CheckoutExpiryPolicy::isExpired($current)
            && $proofModel::where('order_id', $current->id)->whereIn('status', ['pending', 'verified'])->exists();
        $outsideWindow = $current && CheckoutExpiryPolicy::isExpired($current) && !$current->pay_status
            && !$current->payfast_paid && !$current->wallet_debited && !$current->payfast_handed_off_at && !$pendingProof;
        if ($expired || !$current || $current->status === 'cancelled' || $current->withdrawn_at || $outsideWindow) {
            throw ValidationException::withMessages(['payment' => 'This unpaid checkout expired after five minutes. Start registration again. No wallet funds were deducted.']);
        }
    }

    public function expireAll(): array
    {
        $counts = ['registration' => 0, 'team' => 0, 'skipped' => 0];
        foreach (['registration' => RegistrationOrder::class, 'team' => TeamPaymentOrder::class] as $kind => $model) {
            // Prioritise newly expired drafts; review the older backlog fairly
            // without rescanning every historical order each minute.
            $recent = $this->candidateQuery($kind)->where('created_at', '>', now()->subMinutes(15))->orderBy('id')->limit(250)->get();
            $cursorKey = 'checkout-expiry:'.$kind.':backlog-cursor';
            $cursor = (int) Cache::get($cursorKey, 0);
            $backlog = $this->candidateQuery($kind)->where('created_at', '<=', now()->subMinutes(15));
            $older = (clone $backlog)->where('id', '>', $cursor)->orderBy('id')->limit(250)->get();
            if ($older->isEmpty() && $cursor > 0) $older = $backlog->orderBy('id')->limit(250)->get();
            foreach ($recent->concat($older) as $candidate) {
                    try {
                        $order = $candidate;
                        if ($order && $this->expire($order)) $counts[$kind]++;
                        else $counts['skipped']++;
                    } catch (\Illuminate\Validation\ValidationException|\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                        $counts['skipped']++;
                        Log::warning('Unpaid checkout expiry skipped an inconsistent lifecycle', ['kind' => $kind, 'order_id' => $candidate->id]);
                    }
            }
            if ($older->isNotEmpty()) Cache::put($cursorKey, (int) $older->last()->id, now()->addDay());
        }
        return $counts;
    }

    public function candidateCounts(): array
    {
        return [
            'registration' => $this->candidateQuery('registration')->count(),
            'team' => $this->candidateQuery('team')->count(),
        ];
    }

    private function candidateQuery(string $kind): \Illuminate\Database\Eloquent\Builder
    {
        $model = $kind === 'registration' ? RegistrationOrder::class : TeamPaymentOrder::class;
        $query = $model::where('created_at', '<=', CheckoutExpiryPolicy::cutoff())
            ->where(fn ($query) => $query->whereNull('pay_status')->orWhere('pay_status', 0))
            ->where(fn ($query) => $query->whereNull('payfast_paid')->orWhere('payfast_paid', 0))
            ->where(fn ($query) => $query->whereNull('wallet_debited')->orWhere('wallet_debited', 0))
            ->whereNull('payfast_handed_off_at')->whereNull('payfast_pf_payment_id');
        if ($kind === 'registration') {
            $query->where(fn ($query) => $query->whereNull('status')->orWhere('status', '!=', 'cancelled'))
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('trial_payment_proofs')->whereColumn('trial_payment_proofs.order_id', 'registration_orders.id')->whereIn('status', ['pending', 'verified']))
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('registration_manual_receipts')->whereColumn('registration_manual_receipts.order_id', 'registration_orders.id'));
        } else {
            $query->whereNull('withdrawn_at')
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('trial_participation_proofs')->whereColumn('trial_participation_proofs.order_id', 'team_payment_orders.id')->whereIn('status', ['pending', 'verified']))
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('trial_participation_receipts')->whereColumn('trial_participation_receipts.order_id', 'team_payment_orders.id'));
        }
        return $query;
    }
}
