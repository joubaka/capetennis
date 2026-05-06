<x-mail::message>
# Bank Refund Processed

@php
    $player       = $registration->players->first();
    $playerName   = $player ? trim($player->name . ' ' . $player->surname) : 'Player';
    $event        = $registration->categoryEvent?->event;
    $eventName    = $event?->name ?? 'the event';
    $categoryName = $registration->categoryEvent?->category?->name ?? '';
@endphp

Hi {{ $playerName }},

Your bank refund for **{{ $eventName }}** has been processed.

---

**Event:** {{ $eventName }}
**Category:** {{ $categoryName }}
**Refund method:** Bank transfer
**Amount paid:** R{{ number_format($registration->refund_gross, 2) }}
@if($registration->refund_fee > 0)
**Refund fee:** R{{ number_format($registration->refund_fee, 2) }}
@endif
**Amount refunded:** R{{ number_format($registration->refund_net, 2) }}
**Processed on:** {{ $registration->refunded_at?->format('d M Y H:i') ?? now()->format('d M Y H:i') }}

---

Please allow 1–3 business days for the funds to reflect in your bank account.

If you have any questions, please contact us at [support@capetennis.co.za](mailto:support@capetennis.co.za) and quote your registration ID: **#{{ $registration->id }}**.

Thanks,
{{ config('app.name') }}
</x-mail::message>
