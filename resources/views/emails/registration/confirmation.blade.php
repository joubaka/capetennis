<x-mail::message>
# Registration Confirmed

@php
    $firstItem = $order->items->first();
    $event     = $firstItem?->category_event?->event;
    $eventName = $event?->name ?? 'the event';
    $user      = $order->user;
    $userName  = $user?->name ?? 'Player';
@endphp

Hi {{ $userName }},

Your registration for **{{ $eventName }}** has been confirmed.

---

**Registration Details**

@foreach($order->items as $item)
@php
    $player   = \App\Models\Player::find($item->player_id);
    $category = $item->category_event?->category?->name ?? '—';
    $fee      = number_format((float) $item->item_price, 2);
@endphp
- **Player:** {{ $player ? trim($player->name . ' ' . $player->surname) : '—' }}
  **Category:** {{ $category }}
  **Entry Fee:** R{{ $fee }}
@endforeach

---

@if($order->payfast_paid || (float)$order->payfast_amount_due === 0)
**Payment Status:** Paid ✅
@else
**Payment Status:** Pending
@endif

If you have any questions, please contact us at [support@capetennis.co.za](mailto:support@capetennis.co.za).

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
