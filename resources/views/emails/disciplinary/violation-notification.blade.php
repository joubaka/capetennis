@component('mail::message')
# Disciplinary Violation Recorded

A disciplinary violation has been recorded for **{{ $player->full_name }}**.

@component('mail::table')
| Field | Value |
|:------|:------|
| **Player** | {{ $player->full_name }} |
| **Violation Type** | {{ $violation->violationType->name ?? '—' }} |
| **Category** | {{ ucfirst(str_replace('_', ' ', $violation->violationType->category ?? '—')) }} |
| **Date** | {{ $violation->violation_date->format('d M Y') }} |
| **Points Assigned** | {{ $violation->points_assigned }} |
| **Penalty Type** | {{ $violation->penalty_type ? ucfirst($violation->penalty_type) : '—' }} |
| **Recorded By** | {{ $recorder?->name ?? '—' }} |
@if($violation->event_id)
| **Event** | {{ $violation->event?->name ?? 'Event #' . $violation->event_id }} |
@endif
@if($violation->notes)
| **Notes** | {{ $violation->notes }} |
@endif
@endcomponent

@component('mail::button', ['url' => url('/backend/disciplinary/player/' . $player->id), 'color' => 'red'])
View Player Disciplinary Record
@endcomponent

If you have any questions, please contact us at [support@capetennis.co.za](mailto:support@capetennis.co.za).

Thanks,<br>
{{ config('app.name') }}
@endcomponent
