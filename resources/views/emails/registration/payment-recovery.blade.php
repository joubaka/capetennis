<x-mail::message>
@include('emails._capez-header')
# Registration payment required

@php
    $snapshot = $recovery->mail_snapshot;
@endphp

Hi {{ $snapshot['recipient_name'] ?: 'Player' }},

Due to an error in our registration system, your registration for **{{ $snapshot['event'] }}** was accepted without requesting payment. We apologise for the inconvenience. The outstanding amount is **R{{ $snapshot['amount'] }}**.

<x-mail::button :url="$paymentUrl">
Pay securely with PayFast
</x-mail::button>

This secure link expires after seven days and requires you to sign in to the Cape Tennis account that made the registration.

If you have any questions, please contact the event convener{{ $snapshot['contact'] ? ' at '.$snapshot['contact'] : '' }}.

Thanks,<br>
Cape Tennis
</x-mail::message>
