<x-mail::message>
# Bank Details Required for Your Refund

@php
    $player     = $registration->players->first();
    $playerName = $player ? trim($player->name . ' ' . $player->surname) : 'Player';
    $event      = $registration->categoryEvent?->event;
    $eventName  = $event?->name ?? 'the event';
@endphp

Hi {{ $playerName }},

We need your bank account details to process your refund of **R{{ number_format($registration->refund_net, 2) }}** for **{{ $eventName }}**.

Please click the button below to securely submit your bank details. This link is valid for **7 days**.

<x-mail::button :url="$signedUrl" color="primary">
Submit My Bank Details
</x-mail::button>

---

**Refund summary:**
- **Event:** {{ $eventName }}
- **Amount to refund:** R{{ number_format($registration->refund_net, 2) }}
- **Registration ID:** #{{ $registration->id }}

---

Once you submit your details, our team will process the refund within 1–3 business days.

If you have any questions, contact us at [support@capetennis.co.za](mailto:support@capetennis.co.za).

Thanks,
{{ config('app.name') }}
</x-mail::message>
