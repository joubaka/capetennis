@component('mail::message')
# Suspension Triggered

A player has accumulated enough suspension points to trigger an automatic suspension.

**Player:** {{ $player->full_name }}
**Suspension Number:** {{ $suspension->suspension_number }}
**Duration:** {{ $suspension->duration_months }} months
**Starts:** {{ $suspension->starts_at->format('d M Y') }}
**Ends:** {{ $suspension->ends_at->format('d M Y') }}

@component('mail::button', ['url' => url('/backend/disciplinary/player/' . $player->id), 'color' => 'red'])
View Player Record
@endcomponent

Please review the player's disciplinary record and notify them of their suspension.

If the player has any questions, they can contact us at [support@capetennis.co.za](mailto:support@capetennis.co.za).

Thanks,<br>
{{ config('app.name') }}
@endcomponent
