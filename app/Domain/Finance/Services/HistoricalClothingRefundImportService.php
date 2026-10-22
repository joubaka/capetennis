<?php

namespace App\Domain\Finance\Services;

use App\Models\ClothingOrder;
use App\Models\ClothingRefund;
use App\Models\ClothingRefundHistoricalImport;
use App\Models\Event;
use App\Models\EventRegion;
use App\Models\User;
use App\Support\FinanceMutationScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Copies verified historical external evidence locally; never executes a refund. */
class HistoricalClothingRefundImportService
{
    public function import(Event $event, User $actor, array $snapshot, bool $apply = false): array
    {
        $this->assertLocalDatabase();
        abort_unless($actor->hasRole('super-user'), 403);
        $source = Validator::make($snapshot, [
            'source_system' => ['required', 'in:cape-tennis-live'],
            'source_refund_id' => ['required', 'integer', 'min:1'],
            'source_url' => ['required', 'url:https', 'max:500'],
            'source_timezone' => ['required', 'timezone'],
            'source_request_token' => ['required', 'uuid'],
            'source_request_fingerprint' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'source_updated_at' => ['required', 'date', 'before_or_equal:now'],
            'event_id' => ['required', 'integer'], 'event_name' => ['required', 'string'],
            'order_id' => ['required', 'integer'], 'region_id' => ['required', 'integer'],
            'player_id' => ['required', 'integer'], 'player_name' => ['required', 'string'],
            'payer_id' => ['required', 'integer'], 'payer_email' => ['required', 'email'],
            'original_pf_payment_id' => ['required', 'string', 'max:100'],
            'paid_gross' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'payfast_fee' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'payfast_amount_due' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'refund_status' => ['required', 'in:completed'], 'refund_method' => ['required', 'in:payfast'],
            'refund_gross' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'refund_fee' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'refund_net' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'reason' => ['required', 'string', 'max:500'],
            'external_reference' => ['present', 'nullable', 'string', 'max:120'],
            'requested_at' => ['required', 'date', 'before_or_equal:now'],
            'refunded_at' => ['required', 'date', 'before_or_equal:now'],
            'requested_actor' => ['present', 'nullable', 'array'],
            'requested_actor.source_user_id' => ['required_with:requested_actor', 'integer', 'min:1'],
            'requested_actor.name' => ['required_with:requested_actor', 'string', 'max:255'],
            'requested_actor.email' => ['nullable', 'email'],
            'completed_actor' => ['present', 'nullable', 'array'],
            'completed_actor.source_user_id' => ['required_with:completed_actor', 'integer', 'min:1'],
            'completed_actor.name' => ['required_with:completed_actor', 'string', 'max:255'],
            'completed_actor.email' => ['nullable', 'email'],
            'clothing_deadline_at' => ['present', 'nullable', 'date'],
            'late_refund_note' => ['present', 'nullable', 'string', 'max:1000'],
            'reconciliation_status' => ['present', 'nullable', 'in:open,resolved'],
            'reconciliation_note' => ['present', 'nullable', 'string', 'max:1000'],
            'reconciled_at' => ['present', 'nullable', 'date'],
            'reconciled_by_source_user_id' => ['present', 'nullable', 'integer'],
            'provider_attempt' => ['required', 'array'],
            'provider_attempt.source_attempt_id' => ['required', 'integer', 'min:1'],
            'provider_attempt.source_refund_id' => ['required', 'integer', 'min:1'],
            'provider_attempt.source_order_id' => ['required', 'integer', 'min:1'],
            'provider_attempt.status' => ['required', 'in:confirmed'],
            'provider_attempt.pf_payment_id' => ['required', 'string', 'max:100'],
            'provider_attempt.amount' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'provider_attempt.requested_by_source_user_id' => ['required', 'integer', 'min:1'],
            'provider_attempt.confirmed_at' => ['required', 'date', 'before_or_equal:now'],
            'provider_attempt.created_at' => ['sometimes', 'date'],
            'provider_attempt.updated_at' => ['sometimes', 'date'],
            'source_order' => ['sometimes', 'array'],
            'verification' => ['sometimes', 'array:method,source_database,verified_date,production_writes,authorization'],
            'verification.method' => ['sometimes', 'string', 'max:500'],
            'verification.source_database' => ['sometimes', 'string', 'max:100'],
            'verification.verified_date' => ['sometimes', 'date'],
            'verification.production_writes' => ['sometimes', 'boolean'],
            'verification.authorization' => ['sometimes', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.source_order_item_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.local_order_item_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.item_name' => ['required', 'string'], 'items.*.size_name' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:0'],
            'items.*.amount' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'items.*.source_refund_item_id' => ['sometimes', 'integer', 'min:1'],
            'items.*.catalogue_item_id' => ['sometimes', 'integer', 'min:1'],
            'items.*.size_id' => ['sometimes', 'integer', 'min:1'],
            'items.*.purchased_quantity' => ['sometimes', 'integer', 'min:1'],
            'items.*.unit_price' => ['sometimes', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'items.*.line_total' => ['sometimes', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'items.*.created_at' => ['sometimes', 'date'], 'items.*.updated_at' => ['sometimes', 'date'],
        ])->validate();
        // Source database values require full timestamp evidence. A screenshot
        // showing only a date is insufficient for this archival import.
        foreach (['requested_at', 'refunded_at', 'source_updated_at', 'paid_at'] as $field) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', $source[$field])) {
                $this->invalid($field, 'Obtain the exact original database timestamp before importing this refund.');
            }
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', $source['provider_attempt']['confirmed_at'])) {
            $this->invalid('provider_attempt.confirmed_at', 'Obtain the exact original provider confirmation timestamp.');
        }
        if (($source['verification']['production_writes'] ?? false) !== false) {
            $this->invalid('verification', 'Historical source verification must be read-only.');
        }
        $requestedAt = $this->sourceTime($source['requested_at'], $source['source_timezone']);
        $refundedAt = $this->sourceTime($source['refunded_at'], $source['source_timezone']);
        $updatedAt = $this->sourceTime($source['source_updated_at'], $source['source_timezone']);
        $confirmedAt = $this->sourceTime($source['provider_attempt']['confirmed_at'], $source['source_timezone']);
        $paidAt = $this->sourceTime($source['paid_at'], $source['source_timezone']);
        if ($requestedAt->gt($refundedAt) || $refundedAt->gt($updatedAt) || $updatedAt->gt(now()) || $requestedAt->gt(now())
            || $confirmedAt->lt($requestedAt) || $confirmedAt->gt($refundedAt) || $paidAt->gt($requestedAt)) {
            $this->invalid('refunded_at', 'Original refund timestamps are inconsistent.');
        }
        if ((int) $source['event_id'] !== (int) $event->id || $source['event_name'] !== $event->name) {
            $this->invalid('event_id', 'The source event does not match the selected local event.');
        }
        $gross = EventExpenseAmounts::cents($source['refund_gross']);
        $fee = EventExpenseAmounts::cents($source['refund_fee']);
        $net = EventExpenseAmounts::cents($source['refund_net']);
        if ($net > EventExpenseAmounts::cents($source['payfast_amount_due'])
            || EventExpenseAmounts::cents($source['payfast_amount_due']) !== EventExpenseAmounts::cents($source['paid_gross'])) {
            $this->invalid('payfast_amount_due', 'This importer supports only verified fully PayFast-funded historical refunds within the original paid amount.');
        }
        $attempt = $source['provider_attempt'];
        if ((int) $attempt['source_refund_id'] !== (int) $source['source_refund_id']
            || (int) $attempt['source_order_id'] !== (int) $source['order_id']
            || $attempt['pf_payment_id'] !== $source['original_pf_payment_id']
            || EventExpenseAmounts::cents($attempt['amount']) !== $net) {
            $this->invalid('provider_attempt', 'Verified provider evidence must match this source refund, order, original payment and exact refund amount.');
        }
        if ($source['reconciliation_status'] !== null || $source['reconciliation_note'] !== null
            || $source['reconciled_at'] !== null || $source['reconciled_by_source_user_id'] !== null) {
            $this->invalid('reconciliation_status', 'This local importer requires an original refund without a separate reconciliation decision.');
        }
        if ($gross <= 0 || $net <= 0 || $gross - $fee !== $net
            || array_sum(array_map(fn ($line) => EventExpenseAmounts::cents($line['amount']), $source['items'])) !== $gross) {
            $this->invalid('refund_net', 'Source refund and item amounts must reconcile exactly in cents.');
        }
        usort($source['items'], fn ($a, $b) => $a['source_order_item_id'] <=> $b['source_order_item_id']);
        $fingerprint = hash('sha256', json_encode($source, JSON_THROW_ON_ERROR));

        return FinanceMutationScope::run('refund_state_write', function () use ($event, $actor, $source, $fingerprint, $requestedAt, $refundedAt, $updatedAt, $apply) {
            return DB::transaction(function () use ($event, $actor, $source, $fingerprint, $requestedAt, $refundedAt, $updatedAt, $apply) {
                $order = ClothingOrder::with(['user', 'player', 'team.category', 'items.refundItems.refund'])
                    ->whereKey($source['order_id'])->lockForUpdate()->first();
                if (! $order || (int) $order->event_id !== (int) $event->id || (int) $order->team?->category?->event_id !== (int) $event->id
                    || (int) $order->team?->region_id !== (int) $source['region_id']
                    || (int) $order->player_id !== (int) $source['player_id'] || $order->player?->full_name !== $source['player_name']
                    || (int) $order->user_id !== (int) $source['payer_id'] || strcasecmp((string) $order->user?->email, $source['payer_email']) !== 0
                    || $order->pay_status !== 1 || $order->status !== 'completed' || ! $order->payfast_paid
                    || $order->wallet_debited || EventExpenseAmounts::cents($order->wallet_reserved ?? 0) !== 0
                    || ! $order->paid_at || ! $order->paid_at->eq($this->sourceTime($source['paid_at'], $source['source_timezone']))
                    || EventExpenseAmounts::cents($order->payfast_amount_due ?? 0) !== EventExpenseAmounts::cents($source['payfast_amount_due'])
                    || (string) ($order->payfast_pf_payment_id ?: $order->pf_id) !== $source['original_pf_payment_id']
                    || ($order->pf_id && (string) $order->pf_id !== $source['original_pf_payment_id'])
                    || EventExpenseAmounts::cents($order->amount_paid ?? $order->total) !== EventExpenseAmounts::cents($source['paid_gross'])
                    || EventExpenseAmounts::cents($order->payfast_fee) !== EventExpenseAmounts::cents($source['payfast_fee'])) {
                    $this->invalid('order_id', 'The local paid order, payer, player, region, amounts or original PayFast identity does not match the source.');
                }
                if (EventRegion::where('event_id', $event->id)->where('region_id', $source['region_id'])->lockForUpdate()->get()->count() !== 1) {
                    $this->invalid('region_id', 'The local event-region association must be unambiguous.');
                }
                $this->verifySourceOrder($order, $source);
                $existing = ClothingRefundHistoricalImport::where('source_system', $source['source_system'])
                    ->where('source_refund_id', (string) $source['source_refund_id'])->lockForUpdate()->first();
                if ($existing) {
                    if (! hash_equals($existing->source_fingerprint, $fingerprint) || (int) $existing->event_id !== (int) $event->id
                        || (int) $existing->clothing_order_id !== (int) $order->id) {
                        $this->invalid('source_refund_id', 'This historical source was already imported with different details.');
                    }
                    return ['refund' => ClothingRefund::findOrFail($existing->clothing_refund_id), 'created' => false, 'preview' => ! $apply];
                }
                // Do not attach provenance to or overwrite an existing local
                // refund merely because its amounts happen to look similar.
                if ($order->refunds()->where('refund_method', 'payfast')->where('refund_status', 'completed')
                    ->where('refund_net', EventExpenseAmounts::decimal(EventExpenseAmounts::cents($source['refund_net'])))
                    ->where('refunded_at', $refundedAt->format('Y-m-d H:i:s'))->exists()) {
                    $this->invalid('source_refund_id', 'A matching completed local refund already exists; reconcile its identity before importing.');
                }
                $reservedRefunds = $order->refunds()->whereIn('refund_status', ['pending', 'completed'])->get();
                if ($reservedRefunds->sum(fn ($refund) => EventExpenseAmounts::cents($refund->refund_net))
                    + EventExpenseAmounts::cents($source['refund_net']) > EventExpenseAmounts::cents($order->payfast_amount_due)) {
                    $this->invalid('refund_net', 'The imported refund and existing pending/completed refunds exceed original PayFast funding.');
                }
                foreach ($source['items'] as $line) {
                    $item = $order->items->firstWhere('id', $line['local_order_item_id']);
                    if (! $item || $item->item_name !== $line['item_name'] || $item->size_name !== $line['size_name']) {
                        $this->invalid('items', 'Each source item must match an exact purchased line on this local order.');
                    }
                    foreach (['catalogue_item_id' => 'clothing_order_item_id', 'size_id' => 'clothing_item_size', 'purchased_quantity' => 'qty'] as $sourceField => $localField) {
                        if (isset($line[$sourceField]) && (int) $line[$sourceField] !== (int) $item->$localField) $this->invalid('items', 'The source catalogue, size or purchased quantity does not match the local paid line.');
                    }
                    foreach (['unit_price' => 'price', 'line_total' => 'line_total'] as $sourceField => $localField) {
                        if (isset($line[$sourceField]) && EventExpenseAmounts::cents($line[$sourceField]) !== EventExpenseAmounts::cents($item->$localField)) $this->invalid('items', 'The source paid line price or total does not match the local order.');
                    }
                    $used = $item->refundItems->filter(fn ($entry) => in_array($entry->refund?->refund_status, ['pending', 'completed'], true));
                    if ((int) $line['quantity'] + $used->sum('quantity') > $item->qty
                        || EventExpenseAmounts::cents($line['amount']) <= 0
                        || EventExpenseAmounts::cents($line['amount']) + $used->sum(fn ($entry) => EventExpenseAmounts::cents($entry->amount)) > EventExpenseAmounts::cents($item->line_total)) {
                        $this->invalid('items', 'The historical allocation exceeds the purchased quantity or unrefunded item amount.');
                    }
                }
                $requester = $this->localActor($source['requested_actor']);
                $completer = $this->localActor($source['completed_actor']);
                if (! $apply) return ['refund' => null, 'created' => false, 'preview' => true];
                $refund = $order->refunds()->create([
                    'event_id' => $event->id, 'payer_id' => $order->user_id,
                    // Zero has no actor relation: source identity unknown or
                    // unmapped. The importing staff member is never substituted.
                    'requested_by' => $requester?->id ?? 0, 'completed_by' => $completer?->id,
                    'requested_by_name' => $source['requested_actor']['name'] ?? null,
                    'completed_by_name' => $source['completed_actor']['name'] ?? null,
                    'request_token' => (string) \Illuminate\Support\Str::uuid(), 'request_fingerprint' => $fingerprint,
                    'refund_status' => 'completed', 'refund_method' => 'payfast',
                    'refund_gross' => EventExpenseAmounts::decimal(EventExpenseAmounts::cents($source['refund_gross'])),
                    'refund_fee' => EventExpenseAmounts::decimal(EventExpenseAmounts::cents($source['refund_fee'])),
                    'refund_net' => EventExpenseAmounts::decimal(EventExpenseAmounts::cents($source['refund_net'])),
                    'reason' => $source['reason'], 'external_reference' => $source['external_reference'],
                    'refunded_at' => $refundedAt, 'late_refund_note' => $source['late_refund_note'],
                    'clothing_deadline_at' => $source['clothing_deadline_at'] ? $this->sourceTime($source['clothing_deadline_at'], $source['source_timezone']) : null,
                ]);
                $refund->forceFill(['created_at' => $requestedAt, 'updated_at' => $updatedAt])->save();
                foreach ($source['items'] as $line) {
                    $refund->items()->create(['clothing_order_item_id' => $line['local_order_item_id'], 'quantity' => $line['quantity'],
                        'amount' => EventExpenseAmounts::decimal(EventExpenseAmounts::cents($line['amount']))]);
                }
                $import = ClothingRefundHistoricalImport::create([
                    'source_system' => $source['source_system'], 'source_refund_id' => (string) $source['source_refund_id'],
                    'source_fingerprint' => $fingerprint, 'clothing_refund_id' => $refund->id,
                    'event_id' => $event->id, 'clothing_order_id' => $order->id, 'source_snapshot' => $source,
                    'imported_by' => $actor->id, 'imported_by_name' => $actor->name, 'imported_at' => now(),
                ]);
                activity('clothing-finance')->performedOn($import)->causedBy($actor)->withProperties([
                    'source_system' => $source['source_system'], 'source_refund_id' => $source['source_refund_id'],
                    'source_fingerprint' => $fingerprint, 'local_refund_id' => $refund->id, 'external_payment_executed' => false,
                ])->log('Historical completed clothing refund evidence imported locally');
                return ['refund' => $refund, 'created' => true, 'preview' => false];
            });
        });
    }

    private function sourceTime(string $value, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::parse($value, $timezone)->setTimezone(config('app.timezone'));
    }

    private function localActor(?array $actor): ?User
    {
        if (! filled($actor['email'] ?? null)) return null;
        $matches = User::whereRaw('LOWER(email) = ?', [strtolower($actor['email'])])->get();
        if ($matches->count() !== 1) $this->invalid('actor', 'The source actor email does not uniquely identify a local account.');
        return $matches->first();
    }

    private function verifySourceOrder(ClothingOrder $order, array $source): void
    {
        $snapshot = $source['source_order'] ?? [];
        $allowed = ['team_id', 'team_event_id', 'pay_status', 'status', 'subtotal', 'total', 'amount_paid', 'paid_at',
            'payment_method', 'wallet_debited', 'wallet_reserved', 'payfast_paid', 'payfast_amount_due'];
        foreach ($snapshot as $field => $value) {
            if (! in_array($field, $allowed, true)) $this->invalid('source_order', 'Unexpected source order field; retain only reviewed payment evidence.');
            if ($field === 'paid_at') {
                $matches = $order->paid_at->eq($this->sourceTime((string) $value, $source['source_timezone']));
            } elseif ($field === 'team_event_id') {
                $matches = (int) $value === (int) $order->team->category->event_id;
            } elseif (in_array($field, ['subtotal', 'total', 'amount_paid', 'wallet_reserved', 'payfast_amount_due'], true)) {
                if (! preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', (string) $value)) $this->invalid('source_order', 'Source order money must have exact two-decimal precision.');
                $matches = EventExpenseAmounts::cents($value) === EventExpenseAmounts::cents($order->$field ?? 0);
            } elseif (in_array($field, ['wallet_debited', 'payfast_paid'], true)) {
                $matches = in_array($value, [0, 1, false, true, '0', '1'], true) && (bool) $value === (bool) $order->$field;
            } else {
                $matches = (string) $value === (string) $order->$field;
            }
            if (! $matches) $this->invalid('source_order', 'The reviewed source order snapshot does not match local paid state.');
        }
    }

    private function assertLocalDatabase(): void
    {
        $connection = DB::connection();
        $isolatedTest = app()->runningUnitTests() && app()->environment('testing')
            && (($connection->getDriverName() === 'sqlite' && $connection->getDatabaseName() === ':memory:')
                || ($connection->getDriverName() === 'mysql' && preg_match('/^ct_testing(?:_|$)/', $connection->getDatabaseName())
                    && in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)));
        $local = app()->environment('local') && $connection->getDriverName() === 'mysql'
            && in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)
            && $connection->getDatabaseName() === 'ct';
        abort_unless($isolatedTest || $local, 403, 'Historical refund import is restricted to the local ct database and isolated tests.');
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
