<x-mail::message>
@include('emails._capez-header')
# Bank Details Required for Your Refund{{ $registrations->count() > 1 ? 's' : '' }}

Hi {{ $userName }},

We need your bank account details to process {{ $registrations->count() === 1 ? 'a refund' : $registrations->count() . ' refunds' }} owed to you.

Please click the button below to securely submit your bank details. This link is valid for **7 days** and covers all the refunds listed below.

<x-mail::button :url="$signedUrl" color="primary">
Submit My Bank Details
</x-mail::button>

---

**Pending refunds:**

@foreach($registrations as $reg)
@php
    $player    = $reg->players->first();
    $playerName = $player ? trim($player->name . ' ' . $player->surname) : 'Player';
    $eventName  = optional($reg->categoryEvent?->event)->name ?? 'Event';
    $category   = optional($reg->categoryEvent?->category)->name ?? '';
@endphp
- **{{ $playerName }}** — {{ $eventName }}{{ $category ? ' (' . $category . ')' : '' }} — **R{{ number_format($reg->refund_net, 2) }}**
@endforeach

**Total: R{{ number_format($registrations->sum('refund_net'), 2) }}**

---

Once you submit your details, our team will process the refund(s) within 1–3 business days.

If you have any questions, contact us at [support@capetennis.co.za](mailto:support@capetennis.co.za).

Thanks,
Cape Tennis
</x-mail::message>
