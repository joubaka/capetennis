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
    <input type="hidden" name="amount" value="{{ number_format((float)$amount, 2, '.', '') }}">
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

    @php
        $formFields = array_filter([
            'merchant_id'  => $payfast->id,
            'merchant_key' => $payfast->key,
            'return_url'   => $return_url,
            'cancel_url'   => $cancel_url,
            'notify_url'   => $notify_url,
            'amount'       => number_format((float)$amount, 2, '.', ''),
            'item_name'    => $event ? $event->name : 'Event Registration',
            'custom_int1'  => $categoryEvent ? (string)$categoryEvent->id : null,
            'custom_int2'  => $player ? (string)$player->id : null,
            'custom_int3'  => $event ? (string)$event->id : null,
            'custom_int4'  => auth()->check() ? (string)auth()->id() : null,
            'custom_int5'  => $orderId ? (string)$orderId : null,
            'custom_str1'  => $category ? $category->name : null,
            'custom_str2'  => $player ? trim($player->name . ' ' . $player->surname) : null,
            'custom_str3'  => $event ? $event->name : null,
            'custom_str4'  => auth()->check() ? auth()->user()->name : null,
        ], fn($v) => $v !== null && $v !== '');
    @endphp
    <input type="hidden" name="signature" value="{{ $payfast->generateFormSignature($formFields) }}">
</form>

<script>
    document.getElementById('payfastForm').submit();
</script>

