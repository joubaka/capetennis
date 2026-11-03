<?php

namespace App\Domain\Finance\Services;

use App\Models\Event;
use App\Models\EventConvenor;
use App\Models\EventPayout;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventPayoutRecordingService
{
    /** Record evidence of an already-paid registration payout; never transfer funds. */
    public function record(Event $event, User $actor, array $data): EventPayout
    {
        abort_unless($actor->hasRole('super-user'), 403);
        validator($data, [
            'idempotency_key' => ['required', 'uuid'],
            'amount' => ['required', 'regex:/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/D'],
            'convenor_id' => ['nullable', 'integer'],
            'recipient_user_id' => ['nullable', 'integer', 'required_without:convenor_id'],
            'description' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:150'],
            'payment_method' => ['required', 'in:bank_transfer,cash,eft,other'],
            'paid_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ])->validate();
        $cents = EventExpenseAmounts::cents($data['amount']);
        if ($cents < 1 || $cents > 9999999999) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount from R0.01 to R99,999,999.99 with at most two decimals.']);
        }

        return DB::transaction(function () use ($event, $actor, $data, $cents) {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $convenor = null;
            if (! empty($data['convenor_id'])) {
                $convenor = EventConvenor::where('event_id', $event->id)->whereKey($data['convenor_id'])->lockForUpdate()->first();
                if (! $convenor) throw ValidationException::withMessages(['convenor_id' => 'Choose a convenor assigned to this event.']);
                $recipient = $convenor->user;
            } else {
                $recipient = $event->admins()->where('users.id', $data['recipient_user_id'])->first();
                if ($recipient) {
                    $directors = EventConvenor::where('event_id', $event->id)->where('user_id', $recipient->id)->lockForUpdate()->get();
                    if ($directors->count() > 1) throw ValidationException::withMessages(['recipient_user_id' => 'Choose the specific convenor assignment for this recipient.']);
                    $convenor = $directors->first();
                }
            }
            if (! $recipient) throw ValidationException::withMessages(['recipient_user_id' => 'Choose a current convenor or administrator for this event.']);

            $fields = [
                'event_id' => $event->id, 'paid_by' => $actor->id,
                'convenor_id' => $convenor?->id, 'recipient_user_id' => $recipient->id,
                'amount' => EventExpenseAmounts::decimal($cents),
                'description' => trim((string) ($data['description'] ?? '')) ?: null,
                'reference' => trim((string) ($data['reference'] ?? '')) ?: null,
                'payment_method' => $data['payment_method'], 'paid_at' => $data['paid_at'],
                'purpose' => 'registration_payout', 'funding_source' => 'registration_entries',
            ];
            $existing = EventPayout::where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($existing) {
                $existing->fill($fields);
                if ($existing->isDirty(array_keys($fields))) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This submission was already recorded with different details or by another administrator. Reload before recording another payout.']);
                }
                return $existing;
            }
            $payout = EventPayout::create($fields + [
                'idempotency_key' => $data['idempotency_key'],
                'recipient_name' => $recipient->name, 'recorded_by_name' => $actor->name,
            ]);
            $this->audit($payout, $actor, 'registration_payout.recorded', null);
            activity('payout')->performedOn($payout)->causedBy($actor)
                ->withProperties($fields + ['action' => 'recorded', 'idempotency_key' => $data['idempotency_key']])
                ->log('Already-paid registration payout recorded.');

            return $payout;
        });
    }

    /** Correct an incorrect record by appending an opposite entry, retaining both. */
    public function reverse(EventPayout $payout, User $actor, array $data): EventPayout
    {
        abort_unless($actor->hasRole('super-user'), 403);
        validator($data, ['reason' => ['required', 'string', 'min:5', 'max:1000'], 'confirm' => ['accepted']])->validate();
        $reason = trim($data['reason']);
        if (mb_strlen($reason) < 5) throw ValidationException::withMessages(['reason' => 'Explain why this payout record is incorrect.']);

        return DB::transaction(function () use ($payout, $actor, $reason) {
            Event::whereKey($payout->event_id)->lockForUpdate()->firstOrFail();
            $payout = EventPayout::whereKey($payout->id)->lockForUpdate()->firstOrFail();
            if (! in_array($payout->purpose, [null, '', 'registration_payout'], true) || $payout->settlement_details || (float) $payout->amount <= 0) {
                throw ValidationException::withMessages(['payout' => 'This settlement cannot be corrected here. Linked director and clothing settlements require their own reconciliation.']);
            }
            $existing = EventPayout::where('reversal_of_id', $payout->id)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->paid_by !== (int) $actor->id || $existing->reversal_reason !== $reason) {
                    throw ValidationException::withMessages(['payout' => 'This payout already has an accounting correction.']);
                }
                return $existing;
            }
            $reversal = EventPayout::create([
                'event_id' => $payout->event_id, 'convenor_id' => $payout->convenor_id,
                'recipient_user_id' => $payout->recipient_user_id, 'recipient_name' => $payout->recipient_name,
                'amount' => '-'.$payout->amount, 'purpose' => 'payout_reversal',
                'funding_source' => 'registration_entries', 'reversal_of_id' => $payout->id,
                'reversal_reason' => $reason, 'description' => 'Accounting correction of payout #'.$payout->id,
                'payment_method' => 'accounting_correction', 'reference' => $payout->reference,
                'paid_by' => $actor->id, 'recorded_by_name' => $actor->name, 'paid_at' => now(),
                'idempotency_key' => (string) Str::uuid(),
            ]);
            $this->audit($reversal, $actor, 'registration_payout.corrected', $reason);
            activity('payout')->performedOn($reversal)->causedBy($actor)
                ->withProperties(['action' => 'accounting_correction', 'event_id' => $payout->event_id,
                    'original_payout_id' => $payout->id, 'amount' => $reversal->amount, 'reason' => $reason])
                ->log('Incorrect payout record reversed; no funds transferred.');
            return $reversal;
        });
    }

    private function audit(EventPayout $payout, User $actor, string $action, ?string $reason): void
    {
        $auditId = app(AuditWriter::class)->record([
            'category' => 'business', 'action' => $action, 'actor' => $actor,
            'subject' => $payout, 'event_id' => $payout->event_id, 'reason' => $reason,
            'after' => $payout->only(['event_id', 'convenor_id', 'recipient_user_id', 'recipient_name',
                'amount', 'purpose', 'funding_source', 'paid_by', 'paid_at', 'reversal_of_id']),
            'metadata' => ['original_payout_id' => $payout->reversal_of_id, 'moves_money' => false],
        ], critical: true);
        if ($auditId === null) {
            throw new \RuntimeException('The payout could not be recorded because its required audit evidence is unavailable.');
        }
    }
}
