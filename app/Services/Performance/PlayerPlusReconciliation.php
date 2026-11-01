<?php

namespace App\Services\Performance;

use App\Models\{AuditEvent, PlayerPlusOrder, Transaction, User};
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Records evidence of an externally reviewed resolution; never moves money or changes access. */
class PlayerPlusReconciliation
{
    public function receipts(PlayerPlusOrder $order)
    {
        return Transaction::where('custom_str5', 'PlayerPlus')->where('custom_int5', $order->id)
            ->where('custom_int2', $order->player_id)->where('custom_int4', $order->user_id)->orderBy('id');
    }

    public function fingerprint(PlayerPlusOrder $order): string
    {
        $hash = hash_init('sha256');
        $prefix = json_encode([$order->id, $order->player_id, $order->user_id, $order->price_cents], JSON_THROW_ON_ERROR);
        hash_update($hash, substr($prefix, 0, -1).',[');
        $first = true;
        foreach ($this->receipts($order)->lazyById(200, ['id', 'pf_payment_id', 'amount_gross']) as $receipt) {
            if (! $first) hash_update($hash, ',');
            hash_update($hash, json_encode($receipt->toArray(), JSON_THROW_ON_ERROR));
            $first = false;
        }
        hash_update($hash, ']]');
        return hash_final($hash);
    }

    public function reviews(PlayerPlusOrder $order)
    {
        return AuditEvent::where('action', 'player-plus.reconciliation-reviewed')
            ->where('subject_type', PlayerPlusOrder::class)->where('subject_id', (string) $order->id)->latest('id');
    }

    public function review(PlayerPlusOrder $order, User $actor, string $fingerprint, string $reference, string $note): void
    {
        abort_unless($actor->hasRole('super-user'), 403);
        DB::transaction(function () use ($order, $actor, $fingerprint, $reference, $note): void {
            $order = PlayerPlusOrder::lockForUpdate()->findOrFail($order->id);
            if (! filled($order->reconciliation_reason) || ! hash_equals($this->fingerprint($order), $fingerprint)) {
                throw ValidationException::withMessages(['review' => 'Payment evidence changed. Reload this review before recording a resolution.']);
            }
            if ($this->reviews($order)->where('metadata->payment_fingerprint', $fingerprint)->exists()) return;
            if (mb_strlen(trim($reference)) < 3 || mb_strlen($reference) > 150 || mb_strlen(trim($note)) < 10 || mb_strlen($note) > 1500) {
                throw ValidationException::withMessages(['review' => 'Provide an external evidence reference and a resolution note.']);
            }
            $audit = app(AuditWriter::class)->record(['category' => 'business', 'action' => 'player-plus.reconciliation-reviewed',
                'subject' => $order, 'actor' => $actor, 'reason' => $note,
                'metadata' => ['payment_fingerprint' => $fingerprint, 'external_reference' => trim($reference),
                    'financial_state_changed' => false]], true);
            if (! $audit) throw new \RuntimeException('Resolution evidence could not be recorded. No review was completed.');
        }, 3);
    }
}
