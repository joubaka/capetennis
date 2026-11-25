<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EventFormatTransition
{
    /** Reclassification is setup-only; historical workflow evidence must stay attached. */
    public function assertAllowed(Event $event, int $target): void
    {
        if (! $event->exists || (int) $event->getRawOriginal('eventType') === $target) return;

        $targetType = \App\Models\EventType::find($target);
        if (! $targetType) {
            throw ValidationException::withMessages(['eventType' => 'Select a valid event type.']);
        }

        // Canonical camp setup normalizes legacy Camp IDs without changing admission.
        $targetEvent = clone $event;
        $targetEvent->setRelation('eventTypeModel', $targetType);
        if ($event->isCamp() && $targetEvent->isCamp()) return;

        $tables = ['event_nominations', 'draws', 'team_payment_orders', 'clothing_orders',
            'transactions_pf', 'event_expenses', 'event_income_items', 'event_payouts', 'masters_invitation_batches', 'interprovincial_trial_invitation_batches',
            'team_selection_imports', 'trial_participations', 'camp_configurations', 'camp_nominees'];
        foreach ($tables as $table) {
            if (DB::table($table)->where('event_id', $event->id)->exists()) $this->reject();
        }
        $divisions = DB::table('category_events')->where('event_id', $event->id)->select('id');
        foreach (['category_event_registrations', 'registration_order_items', 'teams'] as $table) {
            if (DB::table($table)->whereIn('category_event_id', clone $divisions)->exists()) $this->reject();
        }
    }

    private function reject(): never
    {
        throw ValidationException::withMessages(['eventType' => 'This event already has participants, nominations, invitations, draws or financial history. Create a new event with the required format instead.']);
    }
}
