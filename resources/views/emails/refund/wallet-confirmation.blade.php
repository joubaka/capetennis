<x-mail::message>
@include('emails._capez-header')
# Wallet Refund Confirmed

@php
    $player       = $registration->players->first();
    $playerName   = $emailSnapshot['player_name'] ?? ($player ? trim($player->name . ' ' . $player->surname) : 'Player');
    $event        = $registration->categoryEvent?->event;
    $eventName    = $emailSnapshot['event_name'] ?? $event?->name ?? 'the event';
    $categoryName = $emailSnapshot['category_name'] ?? $registration->categoryEvent?->category?->name ?? '';
    $gross = $emailSnapshot['gross'] ?? $registration->refund_gross;
    $fee = $emailSnapshot['fee'] ?? $registration->refund_fee;
    $net = $emailSnapshot['net'] ?? $registration->refund_net;
@endphp

Hi {{ $payerName ?? $playerName }},

Your refund for **{{ $eventName }}** has been credited to your Cape Tennis wallet.

@if($refundReason)
The entry for **{{ $playerName }}** has been cancelled.

**Reason:** {{ $refundReason }}
@endif

---

**Event:** {{ $eventName }}
**Category:** {{ $categoryName }}
**Refund method:** Wallet (instant)
**Amount paid:** R{{ number_format($gross, 2) }}
@if($fee > 0)
**Deduction:** R{{ number_format($fee, 2) }}
@endif
**Amount refunded:** R{{ number_format($net, 2) }}
@if($emailSnapshot['preview'] ?? false)
**Refunded on:** Recorded after successful confirmation.
@else
**Refunded on:** {{ $registration->refunded_at?->format('d M Y H:i') ?? now()->format('d M Y H:i') }}
@endif

---

Your wallet balance has been updated and the funds are available immediately for future registrations.

Sign in with the same account used to pay for this registration. At registration checkout, choose **Apply Wallet Balance** and follow the displayed payment options. If further payment is required, checkout will explain the available PayFast option.

@if(count($adminReplyTo))
If you have any questions, reply to this email to contact the event admin.
@else
If you have any questions, please contact us at [support@capetennis.co.za](mailto:support@capetennis.co.za).
@endif

Thanks,
Cape Tennis
</x-mail::message>
