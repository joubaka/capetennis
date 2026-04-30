<x-mail::message>
# Bank Refund Still Pending

@php
    $player       = $registration->players->first();
    $playerName   = $player ? trim($player->name . ' ' . $player->surname) : 'Player';
    $event        = $registration->categoryEvent?->event;
    $eventName    = $event?->name ?? 'the event';
    $categoryName = $registration->categoryEvent?->category?->name ?? '';
    $pendingDays  = $registration->updated_at?->diffInDays(now()) ?? 0;
@endphp

Hi {{ $playerName }},

This is a reminder that your bank refund for **{{ $eventName }}** is still pending.

---

**Event:** {{ $eventName }}
**Category:** {{ $categoryName }}
**Refund method:** Bank transfer
**Amount:** R{{ number_format($registration->refund_net, 2) }}
**Bank:** {{ $registration->refund_bank_name }}
**Account name:** {{ $registration->refund_account_name }}
**Days pending:** {{ $pendingDays }}

---

If you have already received this refund or have any questions, please contact us at
[support@capetennis.co.za](mailto:support@capetennis.co.za) and quote your registration ID: **#{{ $registration->id }}**.

Thanks,
{{ config('app.name') }}
</x-mail::message>
