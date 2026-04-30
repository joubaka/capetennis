<x-mail::message>
# Withdrawal Confirmation

@php
    $registration = $registration;
    $player       = $registration->players->first();
    $playerName   = $player ? trim($player->name . ' ' . $player->surname) : 'Player';
    $event        = $registration->categoryEvent?->event;
    $eventName    = $event?->name ?? 'the event';
    $categoryName = $registration->categoryEvent?->category?->name ?? '';
    $withdrawnAt  = $registration->withdrawn_at?->format('d M Y H:i') ?? now()->format('d M Y H:i');
@endphp

Hi {{ $playerName }},

@if ($initiatedBy === 'admin')
Your registration has been **withdrawn by an event administrator**.
@else
Your withdrawal from **{{ $eventName }}** has been confirmed.
@endif

---

**Event:** {{ $eventName }}  
**Category:** {{ $categoryName }}  
**Withdrawn on:** {{ $withdrawnAt }}  
**Initiated by:** {{ $initiatedBy === 'admin' ? 'Event administrator' : 'You' }}

---

**Refund Summary**

@if ($registration->refund_status === 'completed')
Your refund has been processed.

- **Refund method:** {{ ucfirst($registration->refund_method) }}  
- **Amount paid:** R{{ number_format($registration->refund_gross, 2) }}  
- **Refund fee (PayFast):** R{{ number_format($registration->refund_fee, 2) }}  
- **Amount refunded:** R{{ number_format($registration->refund_net, 2) }}  
- **Refunded on:** {{ $registration->refunded_at?->format('d M Y H:i') ?? '–' }}

@elseif ($registration->refund_status === 'pending')
Your refund is **pending** and will be processed shortly.

- **Refund method:** {{ ucfirst($registration->refund_method) }}  
- **Amount paid:** R{{ number_format($registration->refund_gross, 2) }}  
- **Refund fee (PayFast):** R{{ number_format($registration->refund_fee, 2) }}  
- **Amount to be refunded:** R{{ number_format($registration->refund_net, 2) }}

@else
No refund has been issued for this withdrawal.

@if (! $registration->is_paid)
*(Registration was not paid.)*
@endif
@endif

---

If you have any questions, please contact us at [support@capetennis.co.za](mailto:support@capetennis.co.za).

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
