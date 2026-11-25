<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EventFormatTransition
{
    /** Generic selectors cannot bypass a locked, fresh assessment. Dedicated workflows retain their own conversion rules. */
    public function update(Event $event, array $attributes): void
    {
        // Controllers may already have assigned a validated uploaded logo or legacy scalar.
        $attributes = array_replace($event->getDirty(), $attributes);
        DB::transaction(function () use ($event, $attributes): void {
            $current = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if (array_key_exists('eventType', $attributes)) {
                if ((int) $current->eventType !== (int) $event->getRawOriginal('eventType')) {
                    throw ValidationException::withMessages(['eventType' => 'The event format changed. Reload the event before saving.']);
                }
                $this->assertAllowed($current, (int) $attributes['eventType']);
            }
            $current->forceFill($attributes)->save();
            $event->setRawAttributes($current->getAttributes(), true)->unsetRelation('eventTypeModel');
        });
    }

    /** Reclassification is setup-only; historical workflow evidence must stay attached. */
    public function assertAllowed(Event $event, int $target): void
    {
        if (! $event->exists || (int) $event->getRawOriginal('eventType') === $target) return;

        $targetType = \App\Models\EventType::find($target);
        if (! $targetType) {
            throw ValidationException::withMessages(['eventType' => 'Select a valid event type.']);
        }

        $tables = ['event_nominations', 'draws', 'team_payment_orders', 'clothing_orders',
            'transactions_pf', 'event_expenses', 'event_income_items', 'event_payouts', 'masters_invitation_batches', 'interprovincial_trial_invitation_batches',
            'team_selection_imports', 'event_selection_roster_imports', 'cavaliers_trial_roster_drafts',
            'trial_programmes', 'trial_squad_drafts', 'trial_participations', 'camp_configurations', 'camp_nominees'];
        foreach ($tables as $table) {
            if (DB::table($table)->where('event_id', $event->id)->exists()) $this->reject();
        }
        if (DB::table('wallet_transactions')->where('meta->event_id', $event->id)->exists()) $this->reject();
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
