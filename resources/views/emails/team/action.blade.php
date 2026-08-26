@component('mail::message')
@include('emails._capez-header')
# {{ match($action) {
    'registration' => 'Team registration confirmed',
    'withdrawal' => 'Team player withdrawn',
    'refund_requested' => 'Team refund requested',
    'refund_completed' => 'Team refund completed',
    default => 'Team registration update',
} }}

**Event:** {{ $order->event?->name ?? 'Team event' }}
**Player:** {{ $order->player?->full_name ?? trim(($order->player?->name ?? '').' '.($order->player?->surname ?? '')) }}
**Team:** {{ $order->team?->name ?? ('Team #'.$order->team_id) }}

@if(isset($details['gross']))
**Original payment:** R{{ number_format($details['gross'], 2) }}
@endif
@if(isset($details['fee']))
**Withdrawal fee:** R{{ number_format($details['fee'], 2) }}
@endif
@if(isset($details['net']))
**Refund amount:** R{{ number_format($details['net'], 2) }}
@endif
@if(!empty($details['payfast_net']))
**Returned through PayFast:** R{{ number_format($details['payfast_net'], 2) }}
@endif
@if(!empty($details['wallet_net']))
**Returned to wallet:** R{{ number_format($details['wallet_net'], 2) }}
@endif

@if($action === 'refund_requested')
Your refund request has been recorded and is awaiting processing.
@elseif($action === 'refund_completed')
Your refund has been processed. PayFast refunds may take several business days to reflect.
@elseif($action === 'withdrawal')
The player has been removed from the team registration. Any eligible refund is handled separately.
@else
Payment was confirmed and the player is registered for the team event.
@endif

Thanks,
Cape Tennis
@endcomponent
