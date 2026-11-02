<?php

namespace App\Domain\Finance\Services;

use App\Models\ClothingOrder;
use App\Models\Event;
use App\Models\EventConvenor;
use App\Models\EventExpense;
use App\Models\EventPayout;
use App\Models\EventRegion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

/** Evidence of an already-paid net settlement, never a transfer instruction. */
class EventDirectorNetSettlementService
{
    public function previews(Event $event): array
    {
        $ledger = app(FinancialLedgerService::class)->buildForEvent($event);
        $expenses = EventExpense::where('event_id', $event->id)->whereNotIn('expense_type', ['payfast', 'cape_tennis_fee'])->get();
        $directors = $event->convenors()->with('user')->get();
        $payments = EventPayout::where('event_id', $event->id)->whereIn('purpose', ['expense_reimbursement', 'director_payment'])->get();
        $profit = $ledger['totals']['registration_net'] - $expenses->sum(fn ($expense) => EventExpenseReimbursementService::expenseCents($expense)) / 100;
        $recon = EventDirectorReconciliation::build($directors, $expenses, $payments, $profit);
        $clothing = app(RegionalClothingSettlementService::class)->report($event, $ledger['paymentRows']->merge($ledger['refundRows']));
        $projection = app(ConvenorPayoutProjection::class)->build($event, $recon, $clothing);

        return $projection['cards']->mapWithKeys(function ($card) use ($event, $directors, $clothing) {
            $director = $directors->firstWhere('id', $card['convenor_id']);
            $legs = $card['regions']->filter(fn ($region) => $region['contribution_cents'] !== 0)->map(function ($region) use ($clothing) {
                $report = $clothing->first(fn ($row) => (int) $row['scope']->region_id === (int) $region['region_id']);

                return [
                    'event_region_id' => $report['scope']->id, 'region_id' => $region['region_id'],
                    'region_name' => $region['region_name'], 'manager_id' => $region['manager_id'],
                    'contribution_cents' => $region['contribution_cents'],
                    'pending_cents' => $region['pending_cents'],
                    'kind' => $region['contribution_cents'] < 0 ? 'recovery' : 'payout',
                ];
            })->values()->all();
            $snapshot = [
                'event_id' => $event->id, 'convenor_id' => $director->id, 'recipient_user_id' => $director->user_id,
                'registration_cents' => $card['registration_cents'], 'clothing' => $legs,
                'cash_cents' => $card['combined_cents'],
            ];
            $snapshot['preview_token'] = hash_hmac('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR), config('app.key'));
            $withheld = collect($legs)->where('kind', 'recovery')->sum(fn ($leg) => abs($leg['contribution_cents']));
            $unresolved = $clothing->contains(function ($report) use ($event, $card, $director) {
                if (($report['balance_cents'] === 0 && $report['pending_cents'] === 0)
                    || $card['regions']->contains('region_id', $report['scope']->region_id)) return false;
                $scope = $report['scope'];

                return (int) app(\App\Services\TeamSelection\RegionManagerAccessService::class)->manager($scope)?->id === (int) $director->user_id
                    || \App\Models\EventRegionManager::where('event_region_id', $scope->id)->where('user_id', $director->user_id)->exists()
                    || \App\Models\EventRegionFinancialRecipientLink::where('event_id', $event->id)->where('region_id', $scope->region_id)
                        ->where('target_convenor_id', $director->id)->exists();
            });
            $snapshot['available'] = ! $unresolved && $card['registration_cents'] >= $withheld && $card['combined_cents'] > 0;
            $snapshot['unavailable_reason'] = $unresolved ? 'Review the ambiguous or stale clothing assignment for this director before recording a full settlement.' : null;

            return [$director->id => $snapshot];
        })->all();
    }

    public function record(Event $event, User $actor, array $input): EventPayout
    {
        abort_unless($actor->hasRole('super-user'), 403);
        $data = Validator::make($input, [
            'convenor_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
            'settled_at' => ['required', 'date', 'before_or_equal:now'],
            'reference' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'preview_token' => ['required', 'string', 'size:64'],
            'idempotency_key' => ['required', 'uuid'],
            'settlement_confirmed' => ['required', 'accepted'],
        ])->validate();
        $occurredAt = CarbonImmutable::parse($data['settled_at'])->format('Y-m-d H:i:s');
        $fingerprint = hash('sha256', json_encode([
            'event_id' => $event->id, 'convenor_id' => (int) $data['convenor_id'],
            'amount' => EventExpenseAmounts::decimal(EventExpenseAmounts::cents($data['amount'])),
            'settled_at' => $occurredAt, 'reference' => trim($data['reference']),
            'description' => trim($data['description'] ?? ''), 'preview_token' => $data['preview_token'],
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($event, $actor, $data, $occurredAt, $fingerprint) {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            // Match refund/settlement lock order before reading current clothing liabilities.
            ClothingOrder::where('event_id', $event->id)->orderBy('id')->lockForUpdate()->get(['id']);
            EventRegion::where('event_id', $event->id)->orderBy('id')->lockForUpdate()->get(['id']);
            $director = EventConvenor::with('user')->where('event_id', $event->id)->whereKey($data['convenor_id'])->lockForUpdate()->first();
            if (! $director) {
                throw ValidationException::withMessages(['convenor_id' => 'Choose a director assigned to this event.']);
            }
            $existing = EventPayout::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                if ((int) $existing->event_id !== (int) $event->id || ($existing->settlement_details['request_fingerprint'] ?? null) !== $fingerprint) {
                    throw ValidationException::withMessages(['amount' => 'This submission was already recorded with different details.']);
                }

                return $existing;
            }
            $preview = $this->previews($event)[$director->id] ?? null;
            if (! $preview || ! $preview['available'] || ! hash_equals($preview['preview_token'], $data['preview_token'])
                || $preview['cash_cents'] !== EventExpenseAmounts::cents($data['amount'])) {
                throw ValidationException::withMessages(['amount' => 'The settlement balance has changed. Reload and review the current registration and clothing amounts.']);
            }
            $snapshot = $preview + ['request_fingerprint' => $fingerprint];
            $payout = EventPayout::create([
                'event_id' => $event->id, 'convenor_id' => $director->id,
                'amount' => EventExpenseAmounts::decimal($preview['registration_cents']),
                'purpose' => 'director_payment', 'funding_source' => 'registration_entries',
                'paid_at' => $occurredAt, 'reference' => trim($data['reference']), 'description' => $data['description'] ?? null,
                'recipient_name' => $director->user->name, 'recipient_user_id' => $director->user_id,
                'paid_by' => $actor->id, 'recorded_by_name' => $actor->name,
                'payment_method' => 'recorded_net_settlement', 'idempotency_key' => $data['idempotency_key'],
                'settlement_details' => $snapshot,
            ]);
            foreach ($preview['clothing'] as $leg) {
                app(FinancialLedgerService::class)->recordClothingSettlement($event, $actor, [
                    'event_region_id' => $leg['event_region_id'], 'kind' => $leg['kind'],
                    'amount' => EventExpenseAmounts::decimal(abs($leg['contribution_cents'])),
                    'recipient_user_id' => $director->user_id, 'occurred_at' => $occurredAt,
                    'reference' => trim($data['reference']),
                    'evidence' => $leg['kind'] === 'recovery'
                        ? 'Clothing deficit withheld from the director registration settlement; included in the confirmed net cash payment.'
                        : 'Clothing payable included in the confirmed director net cash payment.',
                    'request_token' => Uuid::uuid5($data['idempotency_key'], 'clothing:'.$leg['event_region_id'])->toString(),
                    'confirmed' => 1,
                ], $payout);
            }

            return $payout;
        });
    }
}
