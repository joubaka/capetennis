<?php

namespace App\Domain\Finance\Services;

use App\Models\Event;
use App\Models\EventPayoutRequest;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EventPayoutRequestService
{
    public function authorize(Event $event, User $actor): void
    {
        abort_unless($actor->hasRole('super-user') || ($actor->hasRole('admin') && $actor->is_event_admin($event->id)), 403);
    }

    public function snapshot(Event $event): array
    {
        $ledger = app(FinancialLedgerService::class)->buildForEvent($event);
        $pending = $ledger['refundRows']->filter(fn ($row) => $row->type === 'refund' && $row->refund_status === 'pending' && ($row->subtype ?? null) !== 'clothing_refund')->count();

        $completion = DB::table('event_payout_completions')->where('event_id', $event->id)->first();
        $completed = $completion && $completion->start_date === $event->start_date?->toDateString()
            && $completion->end_date === $event->end_date?->toDateString()
            && strtolower((string) $event->status) === 'completed'
            && ($event->end_date ?? $event->start_date)?->toDateString() <= today()->toDateString();
        return ['event_id' => $event->id, 'balance_cents' => (int) round($ledger['totals']['registration_balance'] * 100), 'pending_refunds' => $pending,
            'completed' => (bool) $completed];
    }

    public function complete(Event $event, User $actor, array $input): void
    {
        $this->authorize($event, $actor);
        Validator::make($input, ['confirmed' => 'required|accepted'])->validate();
        DB::transaction(function () use ($event, $actor) {
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if (! $event->start_date || $event->start_date->toDateString() > today()->toDateString() || ($event->end_date && $event->end_date->toDateString() > today()->toDateString())) {
                throw ValidationException::withMessages(['completion' => 'An event cannot be completed before its scheduled end date (or start date when no end date is set).']);
            }
            if ($this->snapshot($event)['completed']) return;
            DB::table('event_payout_completions')->updateOrInsert(['event_id' => $event->id], ['completed_by' => $actor->id, 'start_date' => $event->start_date->toDateString(), 'end_date' => $event->end_date?->toDateString(), 'created_at' => now()]);
            $before = $event->status;
            $event->status = 'completed';
            $event->save();
            $this->audit($event, $actor, 'event.payout_completion', ['previous_status' => $before]);
        });
    }

    public function request(Event $event, User $actor, array $input): EventPayoutRequest
    {
        $this->authorize($event, $actor);
        $data = Validator::make($input, ['kind' => 'required|in:interim,final', 'amount' => 'nullable|numeric|decimal:0,2|min:0.01|max:99999999.99', 'reason' => 'required|string|max:2000', 'request_token' => 'required|uuid', 'preview_token' => 'required|string|size:64'])->validate();
        $fingerprint = hash('sha256', json_encode([$event->id, $actor->id, $data], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($event, $actor, $data, $fingerprint) {
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $existing = EventPayoutRequest::where('request_token', $data['request_token'])->first();
            if ($existing) {
                if ($existing->fingerprint !== $fingerprint) throw ValidationException::withMessages(['request' => 'This submission token has already been used with different details.']);
                return $existing;
            }
            if (EventPayoutRequest::where('event_id', $event->id)->whereIn('status', ['pending', 'reviewed'])->exists()) throw ValidationException::withMessages(['request' => 'This event already has an outstanding payout request.']);
            $snapshot = $this->snapshot($event);
            if (! hash_equals($this->token($snapshot), $data['preview_token'])) throw ValidationException::withMessages(['request' => 'The balance or event state changed. Reload and review before submitting.']);
            if ($snapshot['pending_refunds']) throw ValidationException::withMessages(['request' => 'Resolve pending registration refunds before requesting a payout.']);
            if ($data['kind'] === 'final' && ! $snapshot['completed']) throw ValidationException::withMessages(['kind' => 'Complete the event before requesting its final payout.']);
            $amount = $data['kind'] === 'final' ? $snapshot['balance_cents'] : EventExpenseAmounts::cents($data['amount'] ?? '0');
            if ($amount <= 0 || $amount > $snapshot['balance_cents'] || ($data['kind'] === 'interim' && $amount >= $snapshot['balance_cents'])) throw ValidationException::withMessages(['amount' => 'Interim requests must be positive and leave a balance; final requests use the full positive remaining balance.']);
            $request = EventPayoutRequest::create(['event_id' => $event->id, 'requested_by' => $actor->id, 'kind' => $data['kind'], 'amount' => EventExpenseAmounts::decimal($amount), 'reason' => trim($data['reason']), 'request_token' => $data['request_token'], 'fingerprint' => $fingerprint, 'snapshot' => $snapshot]);
            $this->audit($event, $actor, 'event.payout_requested', ['request_id' => $request->id, 'kind' => $request->kind, 'amount' => $request->amount, 'snapshot' => $snapshot]);
            return $request;
        });
    }

    public function review(Event $event, EventPayoutRequest $request, User $actor, array $input): void
    {
        abort_unless($actor->hasRole('super-user'), 403);
        $data = Validator::make($input, ['status' => 'required|in:reviewed,rejected,closed', 'review_note' => 'required|string|max:2000', 'preview_token' => 'required|string|size:64'])->validate();
        DB::transaction(function () use ($event, $request, $actor, $data) {
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $request = EventPayoutRequest::where('event_id', $event->id)->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($request->status === $data['status'] && $request->review_note === trim($data['review_note']) && (int) $request->reviewed_by === (int) $actor->id) return;
            if (! in_array($request->status, ['pending', 'reviewed'], true)) throw ValidationException::withMessages(['request' => 'This request is already closed.']);
            $current = $this->snapshot($event);
            if (! hash_equals($this->token($current), $data['preview_token'])) throw ValidationException::withMessages(['request' => 'The current balance changed. Reload before reviewing.']);
            if ($data['status'] === 'reviewed' && ($request->snapshot != $current || $current['pending_refunds'])) throw ValidationException::withMessages(['request' => 'The request is stale. Reject it with a note and ask for a fresh request.']);
            $request->update(['status' => $data['status'], 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_note' => trim($data['review_note'])]);
            $this->audit($event, $actor, 'event.payout_request_reviewed', ['request_id' => $request->id, 'status' => $request->status, 'note' => $request->review_note]);
        });
    }

    public function token(array $snapshot): string
    {
        return hash_hmac('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR), config('app.key'));
    }

    private function audit(Event $event, User $actor, string $action, array $metadata): void
    {
        if (! app(AuditWriter::class)->record(['actor' => $actor, 'subject' => $event, 'event_id' => $event->id, 'action' => $action, 'metadata' => $metadata], critical: true)) throw new \RuntimeException('A canonical audit record is required.');
    }
}
