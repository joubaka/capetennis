<x-mail::message>
@include('emails._capez-header')
# Withdrawal Summary

There {{ $withdrawals->count() === 1 ? 'was' : 'were' }} **{{ $withdrawals->count() }} {{ Str::plural('withdrawal', $withdrawals->count()) }}** for **{{ $event->name }}** between {{ $periodStart->format('d M Y H:i') }} and {{ $periodEnd->format('d M Y H:i') }}.

<x-mail::table>
| Player | Category | Withdrawn at | Refund status |
| :-- | :-- | :-- | :-- |
@foreach ($withdrawals as $registration)
@php
    $player = $registration->players->first();
    $playerName = $player ? trim($player->name.' '.$player->surname) : ($registration->user?->name ?? 'Unknown player');
@endphp
| {{ $playerName }} | {{ $registration->categoryEvent?->category?->name ?? 'Unknown' }} | {{ $registration->withdrawn_at?->format('d M Y H:i') }} | {{ Str::headline($registration->refund_status ?: 'not refunded') }} |
@endforeach
</x-mail::table>

This summary is sent to Cape Tennis super administrators and the administrators assigned to this event.

Regards,  
Cape Tennis
</x-mail::message>
