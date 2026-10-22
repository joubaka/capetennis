<?php

namespace App\Domain\Finance\Services;

use App\Models\ClothingOrder;
use App\Models\ClothingRefund;
use App\Models\Event;
use App\Models\EventRegion;
use App\Models\RegionalClothingSettlement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Clothing-only accounting. Recording evidence never initiates a payment. */
class RegionalClothingSettlementService
{
    public function report(Event $event, Collection $rows): Collection
    {
        $entries = RegionalClothingSettlement::where('event_id', $event->id)->orderByDesc('occurred_at')->orderByDesc('id')->get();
        $refundQuery = ClothingRefund::with(['order.team.category', 'requestedBy', 'completedBy', 'items.item'])
            ->where('event_id', $event->id)->whereIn('refund_status', ['pending', 'completed']);
        if (\Illuminate\Support\Facades\Schema::hasTable('clothing_refund_historical_imports')) {
            $refundQuery->with('historicalImport');
        }
        $refunds = $refundQuery->get();

        return EventRegion::with('region')->where('event_id', $event->id)->orderBy('id')->get()->groupBy('region_id')->map(function ($associations) use ($event, $rows, $entries, $refunds) {
            // event_regions has no unique event/region constraint. Physical
            // regional funds must appear once even when setup contains duplicates.
            $scope = $associations->first();
            $regionRows = $rows->filter(fn ($row) => $this->belongsToRegion($row, $event, $scope));
            $regionEntries = $entries->where('region_id', $scope->region_id)->values();
            $balance = $this->balance($regionRows, $regionEntries);
            $pending = $regionRows->where('type', 'refund')->where('refund_status', 'pending')->sum(fn ($row) => abs(self::signedCents($row->net)));
            $regionRefunds = $refunds->filter(fn ($refund) => (int) $refund->order?->team?->region_id === (int) $scope->region_id
                && (int) $refund->order?->event_id === (int) $event->id
                && (int) $refund->order?->team?->category?->event_id === (int) $event->id)->map(function ($refund) use ($scope, $regionEntries) {
                    // A current closed switch is not a historical closure timestamp.
                    $refund->after_recorded_payout = $refund->refunded_at && $regionEntries->where('kind', 'payout')
                        ->contains(fn ($entry) => $entry->hasDateOnlyEvidence()
                            ? $entry->occurred_at->startOfDay()->addDay()->lte($refund->refunded_at)
                            : $entry->occurred_at->lt($refund->refunded_at));
                    $refund->after_recorded_deadline = $refund->refunded_at && $refund->clothing_deadline_at
                        && $refund->clothing_deadline_at->lte($refund->refunded_at);
                    return $refund;
                })->values();

            return [
                'scope' => $scope, 'entries' => $regionEntries, 'refunds' => $regionRefunds,
                'association_count' => $associations->count(),
                'recording_available' => $associations->count() === 1 && $scope->region !== null,
                'balance_cents' => $balance, 'pending_cents' => $pending,
                'available_cents' => max(0, $balance - $pending),
                'payout_cents' => $regionEntries->where('kind', 'payout')->sum(fn ($entry) => self::signedCents($entry->amount)),
                'recovery_cents' => $regionEntries->where('kind', 'recovery')->sum(fn ($entry) => self::signedCents($entry->amount)),
                'offset_cents' => $regionEntries->where('kind', 'offset_in')->sum(fn ($entry) => self::signedCents($entry->amount))
                    - $regionEntries->where('kind', 'offset_out')->sum(fn ($entry) => self::signedCents($entry->amount)),
                'net_cents' => $this->balance($regionRows, collect()),
                'shortfall_cents' => $regionEntries->where('kind', 'payout')->sum(fn ($entry) => self::signedCents($entry->funding_shortfall)),
            ];
        });
    }

    public function record(Event $event, User $actor, array $input): RegionalClothingSettlement
    {
        abort_unless($actor->hasRole('super-user'), 403);
        $data = Validator::make($input, [
            'event_region_id' => ['required', 'integer'],
            'kind' => ['required', Rule::in(['payout', 'recovery', 'offset'])],
            'amount' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/', 'numeric', 'min:0.01', 'max:99999999.99'],
            'recipient_name' => ['required', 'string', 'max:150'],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'reference' => ['required', 'string', 'max:150'],
            'evidence' => ['required', 'string', 'min:10', 'max:2000'],
            'request_token' => ['required', 'uuid'],
            'source_event_region_id' => ['nullable', 'required_if:kind,offset', 'integer'],
            'offset_agreement_confirmed' => ['required_if:kind,offset', 'accepted_if:kind,offset'],
            'confirmed' => ['required', 'accepted'],
            'overpayment_confirmed' => ['sometimes', 'accepted'],
            'overpayment_reason' => ['nullable', 'string', 'max:1000'],
        ])->validate();
        if ($data['kind'] !== 'payout' && (! empty($data['overpayment_confirmed']) || filled($data['overpayment_reason'] ?? null))) {
            throw ValidationException::withMessages(['overpayment_confirmed' => 'Only an actual already-paid payout may record an overpayment discrepancy.']);
        }
        $occurredAt = CarbonImmutable::parse($data['occurred_at'])->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        $dateOnly = preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['occurred_at']) === 1;
        $amount = self::signedCents($data['amount']);
        $normalized = [
            'event_id' => $event->id, 'event_region_id' => (int) $data['event_region_id'],
            'kind' => $data['kind'], 'amount' => EventExpenseAmounts::decimal($amount),
            'recipient_name' => trim($data['recipient_name']), 'occurred_at' => $occurredAt,
            'reference' => trim($data['reference']),
            'evidence' => ($dateOnly ? RegionalClothingSettlement::DATE_ONLY_EVIDENCE : RegionalClothingSettlement::DATE_TIME_EVIDENCE).trim($data['evidence']),
            'date_only' => $dateOnly,
            'source_event_region_id' => $data['kind'] === 'offset' ? (int) $data['source_event_region_id'] : null,
            'offset_agreement_confirmed' => $data['kind'] === 'offset',
            'overpayment_confirmed' => ! empty($data['overpayment_confirmed']),
            'overpayment_reason' => trim($data['overpayment_reason'] ?? ''),
        ];
        $fingerprint = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($event, $actor, $data, $normalized, $fingerprint, $amount, $occurredAt, $dateOnly) {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            // Refund request/finalization locks order before region. Use the same
            // order so a newly pending or completed refund cannot be missed.
            ClothingOrder::where('event_id', $event->id)->orderBy('id')->lockForUpdate()->get(['id']);
            $scopes = EventRegion::with('region')->where('event_id', $event->id)->whereIn('id', array_filter([
                $normalized['event_region_id'], $normalized['source_event_region_id'],
            ]))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $target = $scopes->get($normalized['event_region_id']);
            if (! $target || ! $target->region) {
                throw ValidationException::withMessages(['event_region_id' => 'Choose a region assigned to this event.']);
            }
            $this->assertUniqueAssociation($event, $target, 'event_region_id');
            $existing = RegionalClothingSettlement::where('request_token', $data['request_token'])->get();
            if ($existing->isNotEmpty()) {
                if ($existing->contains(fn ($entry) => $entry->request_fingerprint !== $fingerprint || (int) $entry->event_id !== (int) $event->id)) {
                    throw ValidationException::withMessages(['request_token' => 'This submission was already recorded with different details.']);
                }
                return $existing->firstWhere('event_region_id', $target->id);
            }
            $ledger = app(FinancialLedgerService::class)->buildForEvent($event);
            $rows = $ledger['paymentRows']->merge($ledger['refundRows']);
            $report = $this->report($event, $rows)->keyBy(fn ($region) => $region['scope']->id);
            $current = $report->get($target->id);
            if ($data['kind'] !== 'payout' && $current['entries']->contains(fn ($entry) =>
                $entry->occurred_at->isSameDay($occurredAt) && ($dateOnly || $entry->hasDateOnlyEvidence()))) {
                throw ValidationException::withMessages(['occurred_at' => 'The same day contains a settlement with an unknown time. Record recovery or offset evidence on a date whose order is established; do not assume intraday chronology.']);
            }
            $regionRows = $rows->filter(fn ($row) => $this->belongsToRegion($row, $event, $target));
            if ($data['kind'] !== 'payout' && $dateOnly && $regionRows->contains(fn ($row) =>
                ($row->type === 'clothing_payment' || ($row->refund_status ?? null) === 'completed')
                && filled($row->created_at ?? null) && CarbonImmutable::parse($row->created_at)->isSameDay($occurredAt))) {
                throw ValidationException::withMessages(['occurred_at' => 'Same-day clothing receipts or refunds have an unknown order relative to this date-only recovery or offset. Use evidence that establishes the sequence.']);
            }
            $asOf = $this->rowsAsOf($regionRows, $occurredAt, $dateOnly);
            $priorEntries = $this->entriesAsOf($current['entries'], $occurredAt, $dateOnly);
            $liabilityCutoff = $dateOnly ? CarbonImmutable::parse($occurredAt)->endOfDay() : CarbonImmutable::parse($occurredAt);
            $fundingShortfall = 0;
            if ($data['kind'] === 'payout') {
                // Unknown legacy receipt dates cannot authorize a backdated payout.
                $historicPending = $regionRows->filter(fn ($row) => $row->type === 'refund'
                    && ($row->model?->created_at ?? null) && $row->model->created_at->lte($liabilityCutoff)
                    && (! $row->model->refunded_at || $row->model->refunded_at->gt($liabilityCutoff)))
                    ->sum(fn ($row) => abs(self::signedCents($row->net)));
                $fundingShortfall = max(0, $amount - max(0, $this->balance($asOf, $priorEntries) - $historicPending));
                if ($fundingShortfall > 0 && (! $normalized['overpayment_confirmed'] || mb_strlen($normalized['overpayment_reason']) < 10)) {
                    throw ValidationException::withMessages(['amount' => 'The payout exceeds clothing funds available at its actual payment date. To record an actual overpayment, explicitly acknowledge it and explain the discrepancy with payment evidence.']);
                }
            } elseif ($amount > max(0, -$current['balance_cents']) || $amount > max(0, -$this->balance($asOf, $priorEntries))) {
                throw ValidationException::withMessages(['amount' => 'Record only a recovery or agreed offset against the current completed clothing deficit.']);
            }
            $source = null;
            if ($data['kind'] === 'offset') {
                $source = $scopes->get($normalized['source_event_region_id']);
                if (! $source || ! $source->region || (int) $source->region_id === (int) $target->region_id) {
                    throw ValidationException::withMessages(['source_event_region_id' => 'Choose another clothing region in this event with funds available.']);
                }
                $this->assertUniqueAssociation($event, $source, 'source_event_region_id');
                if ($amount > $report->get($source->id)['available_cents']) {
                    throw ValidationException::withMessages(['amount' => 'The source region has insufficient clothing payable after pending refund liabilities.']);
                }
                $sourceRows = $rows->filter(fn ($row) => $this->belongsToRegion($row, $event, $source));
                $sourceAsOf = $this->rowsAsOf($sourceRows, $occurredAt, $dateOnly);
                $sourceEntriesAsOf = $this->entriesAsOf($report->get($source->id)['entries'], $occurredAt, $dateOnly);
                $sourcePendingAsOf = $sourceRows->filter(fn ($row) => $row->type === 'refund'
                    && ($row->model?->created_at ?? null) && $row->model->created_at->lte($liabilityCutoff)
                    && (! $row->model->refunded_at || $row->model->refunded_at->gt($liabilityCutoff)))
                    ->sum(fn ($row) => abs(self::signedCents($row->net)));
                if ($amount > $this->balance($sourceAsOf, $sourceEntriesAsOf) - $sourcePendingAsOf) {
                    throw ValidationException::withMessages(['amount' => 'The offset exceeds source clothing funds available at the actual agreement date.']);
                }
            }
            $base = [
                'event_id' => $event->id, 'amount' => $normalized['amount'], 'recipient_name' => $normalized['recipient_name'],
                'occurred_at' => $occurredAt, 'reference' => $normalized['reference'], 'evidence' => $normalized['evidence'],
                'recorded_by' => $actor->id, 'recorded_by_name' => $actor->name, 'request_token' => $data['request_token'],
                'request_fingerprint' => $fingerprint, 'offset_agreement_confirmed' => $normalized['offset_agreement_confirmed'],
                'funding_shortfall' => EventExpenseAmounts::decimal($fundingShortfall),
                'overpayment_reason' => $normalized['overpayment_reason'] ?: null,
            ];
            $entry = RegionalClothingSettlement::create($base + [
                'event_region_id' => $target->id, 'region_id' => $target->region_id, 'region_name' => $target->region->region_name,
                'kind' => $source ? 'offset_in' : $data['kind'], 'counterparty_event_region_id' => $source?->id,
            ]);
            if ($source) {
                RegionalClothingSettlement::create($base + [
                    'event_region_id' => $source->id, 'region_id' => $source->region_id, 'region_name' => $source->region->region_name,
                    'kind' => 'offset_out', 'counterparty_event_region_id' => $target->id,
                ]);
            }
            activity('clothing-finance')->performedOn($entry)->causedBy($actor)->withProperties($normalized)->log('Clothing settlement evidence recorded');
            return $entry;
        });
    }

    private function belongsToRegion(object $row, Event $event, EventRegion $scope): bool
    {
        $order = $row->order ?? null;
        return ($row->type === 'clothing_payment' || ($row->subtype ?? null) === 'clothing_refund')
            && $order instanceof ClothingOrder && (int) $order->event_id === (int) $event->id
            && (int) $order->team?->category?->event_id === (int) $event->id
            && (int) $order->team?->region_id === (int) $scope->region_id;
    }

    private function assertUniqueAssociation(Event $event, EventRegion $scope, string $field): void
    {
        $associations = EventRegion::where('event_id', $event->id)->where('region_id', $scope->region_id)
            ->orderBy('id')->lockForUpdate()->get(['id']);
        if ($associations->count() !== 1) {
            throw ValidationException::withMessages([$field => 'This physical region has multiple event associations. Review the duplicate setup before recording any clothing settlement.']);
        }
    }

    private function balance(Collection $rows, Collection $entries): int
    {
        return $rows->filter(fn ($row) => $row->type === 'clothing_payment' || ($row->refund_status ?? null) === 'completed')
            ->sum(fn ($row) => self::signedCents($row->net))
            + $entries->sum(fn ($entry) => (in_array($entry->kind, ['payout', 'offset_out'], true) ? -1 : 1) * self::signedCents($entry->amount));
    }

    private function rowsAsOf(Collection $rows, string $date, bool $dateOnly): Collection
    {
        $point = CarbonImmutable::parse($date);
        return $rows->filter(function ($row) use ($point, $dateOnly) {
            if (! filled($row->created_at ?? null)) return false;
            $time = CarbonImmutable::parse($row->created_at);
            if (! $dateOnly) return $time->lte($point);
            // Unknown intraday order: do not fund a payout with same-day income,
            // but include same-day refund obligations conservatively.
            return $row->type === 'clothing_payment' ? $time->lt($point->startOfDay()) : $time->lte($point->endOfDay());
        });
    }

    private function entriesAsOf(Collection $entries, string $date, bool $dateOnly): Collection
    {
        $point = CarbonImmutable::parse($date);
        return $entries->filter(function ($entry) use ($point, $dateOnly) {
            if ($entry->occurred_at->isSameDay($point) && ($dateOnly || $entry->hasDateOnlyEvidence())) {
                // An existing date-only credit cannot fund a precisely timed
                // payment earlier on that day. Unknown-time debits reserve funds.
                return in_array($entry->kind, ['payout', 'offset_out'], true);
            }
            return $entry->occurred_at->lte($point);
        });
    }

    private static function signedCents(mixed $amount): int
    {
        $value = number_format((float) $amount, 2, '.', '');
        return ($value[0] === '-' ? -1 : 1) * EventExpenseAmounts::cents(ltrim($value, '-'));
    }
}
