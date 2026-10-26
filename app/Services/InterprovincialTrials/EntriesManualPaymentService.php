<?php

namespace App\Services\InterprovincialTrials;

use App\Domain\Entries\Services\EntryEligibilityService;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\InterprovincialTrialInvitation;
use App\Models\RegistrationManualReceipt;
use App\Models\RegistrationOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EntriesManualPaymentService
{
    /** Resolve the selected player's existing checkout without creating an entry. */
    public function resolve(CategoryEvent $category, int $playerId, User $actor, array $confirmation = []): ?array
    {
        app(TrialProgrammeService::class)->authorize($category->event, $actor);

        $query = InterprovincialTrialInvitation::query()
            ->where('event_id', $category->event_id)
            ->where('category_event_id', $category->id)
            ->where('player_id', $playerId)
            ->whereNotNull('order_id');
        if (isset($confirmation['existing_order_id'])) {
            $query->where('order_id', $confirmation['existing_order_id']);
        } else {
            $query->where('status', InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT);
        }
        $matches = $query->limit(2)->get();
        if ($matches->isEmpty() && !isset($confirmation['existing_order_id'])) {
            return null;
        }
        if ($matches->count() !== 1) {
            $this->conflict();
        }
        $selected = $matches->sole();

        return DB::transaction(function () use ($category, $playerId, $actor, $confirmation, $selected) {
            // Serialize with public nominee acceptance before canonical payment locks.
            Event::query()->whereKey($category->event_id)->lockForUpdate()->firstOrFail();
            $category = CategoryEvent::query()->lockForUpdate()->findOrFail($category->id);
            $order = RegistrationOrder::query()->lockForUpdate()->findOrFail($selected->order_id);
            $invitation = InterprovincialTrialInvitation::query()->lockForUpdate()->findOrFail($selected->id);
            $items = $order->items()->lockForUpdate()->get();
            $item = $items->count() === 1 ? $items->sole() : null;
            $entry = CategoryEventRegistration::query()
                ->where('category_event_id', $category->id)
                ->where('registration_id', $invitation->registration_id)
                ->lockForUpdate()->first();

            if ((int) $invitation->event_id !== (int) $category->event_id
                || (int) $invitation->category_event_id !== (int) $category->id
                || (int) $invitation->player_id !== $playerId
                || (int) $invitation->order_id !== (int) $order->id
                || !$item || !$entry
                || (int) $item->category_event_id !== (int) $category->id
                || (int) $item->player_id !== $playerId
                || (int) $item->registration_id !== (int) $invitation->registration_id
                || (int) $item->user_id !== (int) $order->user_id
                || (int) $entry->user_id !== (int) $order->user_id
                || !DB::table('event_nominations')->where('id', $invitation->nomination_id)
                    ->where('event_id', $category->event_id)->where('category_event_id', $category->id)->where('player_id', $playerId)->exists()
                || !$entry->registration->players()->where('players.id', $playerId)->exists()
                || $entry->withdrawn_at || $entry->refunded_at
                || !in_array($entry->refund_status, [null, '', 'not_refunded'], true)
                || !in_array($entry->status, ['active', 'pending_checkout'], true)) {
                $this->conflict();
            }
            app(TrialProgrammeService::class)->authorize($category->event, $actor);
            app(EntryEligibilityService::class)->assertAdminCategoryEligible($category, $playerId, $actor, true);

            if (!isset($confirmation['existing_order_id'])
                && app(\App\Domain\Payments\Services\RegistrationPaymentService::class)->expireUnpaidCheckout($order)) {
                return null;
            }

            $amount = number_format((float) $order->total_fee, 2, '.', '');
            if ((float) $amount <= 0 || number_format((float) $item->item_price, 2, '.', '') !== $amount) {
                $this->conflict();
            }
            if (!isset($confirmation['existing_order_id'])) {
                if ($order->payfast_handed_off_at || (float) $order->wallet_reserved !== 0.0 || $order->pay_status || $order->status === 'cancelled') {
                    throw ValidationException::withMessages(['payment' => 'This checkout has payment or lifecycle evidence and cannot be manually settled.']);
                }
                return ['checkout' => ['order_id' => $order->id, 'amount' => $amount]];
            }
            if (number_format((float) $confirmation['confirmed_amount'], 2, '.', '') !== $amount) {
                throw ValidationException::withMessages(['confirmed_amount' => 'The checkout amount changed. Review the payment again.']);
            }
            $receipt = RegistrationManualReceipt::where('order_id', $order->id)->first();
            if ($receipt && ($receipt->reference !== $confirmation['reference'] || $receipt->method !== 'manual'
                || (int) $receipt->event_id !== (int) $category->event_id
                || $invitation->status !== InterprovincialTrialInvitation::PAID_CONFIRMED || !$order->pay_status || !$entry->is_paid)) {
                throw ValidationException::withMessages(['reference' => 'This checkout already has a different receipt. Review its recorded payment.']);
            }
            app(ManualCollectionService::class)->markPaid($order, $actor, $confirmation['reference']);

            return ['entry' => $entry->fresh()];
        }, 3);
    }

    private function conflict(): never
    {
        throw ValidationException::withMessages(['payment' => 'The existing checkout no longer matches this player and category. Refresh Entries and review the checkout.']);
    }
}
