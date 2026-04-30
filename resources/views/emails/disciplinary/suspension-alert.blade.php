@component('mail::message')
# Suspension Notice — {{ $player->full_name }}

Dear {{ $player->full_name }},

Your cumulative disciplinary points have reached the suspension threshold. The following suspension has been placed on your account.

@component('mail::table')
| Field | Value |
|:------|:------|
| **Player** | {{ $player->full_name }} |
| **Suspension #** | {{ $suspension->suspension_number }} |
| **Duration** | {{ $suspension->duration_months }} months |
| **Starts** | {{ $suspension->starts_at->format('d M Y') }} |
| **Ends** | {{ $suspension->ends_at->format('d M Y') }} |
@endcomponent

During this period you are not permitted to participate in sanctioned Cape Tennis events. If you believe this suspension has been issued in error, please contact us.

If you have any questions, please contact us at [support@capetennis.co.za](mailto:support@capetennis.co.za).

Thanks,<br>
{{ config('app.name') }}
@endcomponent
