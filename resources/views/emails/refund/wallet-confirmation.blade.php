<x-mail::message>
# Wallet Refund Confirmed

@php
    $player       = $registration->players->first();
    $playerName   = $player ? trim($player->name . ' ' . $player->surname) : 'Player';
    $event        = $registration->categoryEvent?->event;
    $eventName    = $event?->name ?? 'the event';
    $categoryName = $registration->categoryEvent?->category?->name ?? '';
@endphp

Hi {{ $playerName }},

Your refund for **{{ $eventName }}** has been credited to your Cape Tennis wallet.

---

**Event:** {{ $eventName }}
**Category:** {{ $categoryName }}
**Refund method:** Wallet (instant)
**Amount paid:** R{{ number_format($registration->refund_gross, 2) }}
@if($registration->refund_fee > 0)
**Refund fee (PayFast):** R{{ number_format($registration->refund_fee, 2) }}
@endif
**Amount refunded:** R{{ number_format($registration->refund_net, 2) }}
**Refunded on:** {{ $registration->refunded_at?->format('d M Y H:i') ?? now()->format('d M Y H:i') }}

---

Your wallet balance has been updated and the funds are available immediately for future registrations.

If you have any questions, please contact us at [support@capetennis.co.za](mailto:support@capetennis.co.za).

Thanks,
{{ config('app.name') }}
</x-mail::message>
