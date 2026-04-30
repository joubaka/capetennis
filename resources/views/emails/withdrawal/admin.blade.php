<x-mail::message>
# Player Withdrawal Notification

@php
    $registration = $registration;
    $player       = $registration->players->first();
    $playerName   = $player ? trim($player->name . ' ' . $player->surname) : 'Unknown Player';
    $event        = $registration->categoryEvent?->event;
    $eventName    = $event?->name ?? '–';
    $categoryName = $registration->categoryEvent?->category?->name ?? '–';
    $withdrawnAt  = $registration->withdrawn_at?->format('d M Y H:i') ?? now()->format('d M Y H:i');

    // Player contact
    $playerEmail  = $player?->email ?? $registration->user?->email ?? '–';
    $playerCell   = $player?->cell ?? '–';

    // Who performed the withdrawal
    $actor = $initiatedBy === 'admin' ? 'Event administrator' : ($playerName . ' (player)');
@endphp

A player has **withdrawn** from your event.

---

**Player:** {{ $playerName }}  
**Email:** {{ $playerEmail }}  
**Cell:** {{ $playerCell }}  
**Event:** {{ $eventName }}  
**Category:** {{ $categoryName }}  
**Withdrawn on:** {{ $withdrawnAt }}  
**Initiated by:** {{ $actor }}

---

**Refund Summary**

@if ($registration->refund_status === 'completed')
Refund has been **completed**.

- **Method:** {{ ucfirst($registration->refund_method) }}  
- **Gross:** R{{ number_format($registration->refund_gross, 2) }}  
- **Fee:** R{{ number_format($registration->refund_fee, 2) }}  
- **Net refunded:** R{{ number_format($registration->refund_net, 2) }}  
- **Refunded on:** {{ $registration->refunded_at?->format('d M Y H:i') ?? '–' }}

@elseif ($registration->refund_status === 'pending')
Refund is **pending**.

- **Method:** {{ ucfirst($registration->refund_method) }}  
- **Gross:** R{{ number_format($registration->refund_gross, 2) }}  
- **Fee:** R{{ number_format($registration->refund_fee, 2) }}  
- **Net to be refunded:** R{{ number_format($registration->refund_net, 2) }}

@else
**No refund** has been issued.

@if (! $registration->is_paid)
*(Registration was not paid.)*
@endif
@endif

---

You can view this registration in the [admin panel]({{ url('/backend/event/' . ($event?->id ?? '') . '/entries') }}).

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
