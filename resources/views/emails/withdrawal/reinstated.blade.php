<x-mail::message>
# Registration Reinstated

@php
    $player       = $registration->players->first();
    $playerName   = $player ? trim($player->name . ' ' . $player->surname) : 'Player';
    $event        = $registration->categoryEvent?->event;
    $eventName    = $event?->name ?? 'the event';
    $categoryName = $registration->categoryEvent?->category?->name ?? '';
@endphp

Hi {{ $playerName }},

Good news! Your withdrawal from **{{ $eventName }}** has been cancelled by an event administrator and your registration is now **active** again.

---

**Event:** {{ $eventName }}
**Category:** {{ $categoryName }}
**Status:** Active

---

No refund has been issued. Your original registration remains valid.

If you did not expect this change or have any questions, please contact us at [support@capetennis.co.za](mailto:support@capetennis.co.za).

Thanks,
{{ config('app.name') }}
</x-mail::message>
