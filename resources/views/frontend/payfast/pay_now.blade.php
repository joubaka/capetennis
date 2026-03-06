@php
    use App\Models\RegistrationOrder;
    
    // Load order with relationships if orderId is provided
    $order = null;
    $firstItem = null;
    $categoryEvent = null;
    $event = null;
    $category = null;
    $player = null;
    
    if (isset($orderId) && $orderId) {
        $order = RegistrationOrder::with('items.category_event.event', 'items.category_event.category', 'items.player')->find($orderId);
        if ($order) {
            $firstItem = $order->items->first();
            $categoryEvent = $firstItem?->category_event;
            $event = $categoryEvent?->event;
            $category = $categoryEvent?->category;
            $player = $firstItem?->player;
        }
    }
@endphp

<form id="payfastForm" action="{{ $payfast->url }}" method="post">
    <input type="hidden" name="merchant_id" value="{{ $payfast->id }}">
    <input type="hidden" name="merchant_key" value="{{ $payfast->key }}">
    <input type="hidden" name="return_url" value="{{ $return_url }}">
    <input type="hidden" name="cancel_url" value="{{ $cancel_url }}">
    <input type="hidden" name="notify_url" value="{{ $notify_url }}">
    <input type="hidden" name="amount" value="{{ $amount }}">
    <input type="hidden" name="item_name" value="{{ $event ? $event->name : 'Event Registration' }}">
    
    {{-- PayFast Custom Fields --}}
    @if($categoryEvent)
        <input type="hidden" name="custom_int1" value="{{ $categoryEvent->id }}">
    @endif
    @if($player)
        <input type="hidden" name="custom_int2" value="{{ $player->id }}">
    @endif
    @if($event)
        <input type="hidden" name="custom_int3" value="{{ $event->id }}">
    @endif
    @if(auth()->check())
        <input type="hidden" name="custom_int4" value="{{ auth()->id() }}">
    @endif
    @if($orderId)
        <input type="hidden" name="custom_int5" value="{{ $orderId }}">
    @endif
    
    @if($category)
        <input type="hidden" name="custom_str1" value="{{ $category->name }}">
    @endif
    @if($player)
        <input type="hidden" name="custom_str2" value="{{ trim($player->name . ' ' . $player->surname) }}">
    @endif
    @if($event)
        <input type="hidden" name="custom_str3" value="{{ $event->name }}">
    @endif
    @if(auth()->check())
        <input type="hidden" name="custom_str4" value="{{ auth()->user()->name }}">
    @endif
    
    @if(isset($custom_wallet_reserved))
        <input type="hidden" name="custom_wallet_reserved" value="{{ $custom_wallet_reserved }}">
    @endif
</form>

<script>
    document.getElementById('payfastForm').submit();
</script>

