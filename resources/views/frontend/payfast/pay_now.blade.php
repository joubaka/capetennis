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
        <input type="hidden" name="custom_str4" value="{{ trim(auth()->user()->name) }}">
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
            'custom_str4'  => auth()->check() ? trim(auth()->user()->name) : null,
        ], fn($v) => $v !== null && $v !== '');
    @endphp
    <input type="hidden" name="signature" value="{{ $payfast->generateFormSignature($formFields) }}">
</form>

<script>
    (function() {
        var form = document.getElementById('payfastForm');
        var fields = {};
        form.querySelectorAll('input[type=hidden]').forEach(function(el) {
            fields[el.name] = el.value;
        });
        console.log('[PayFast Form Fields]', fields);
        console.warn('merchant_id  = "' + (fields.merchant_id  || 'EMPTY') + '"');
        console.warn('merchant_key = "' + (fields.merchant_key || 'EMPTY') + '"');
        console.warn('amount       = "' + (fields.amount       || 'EMPTY') + '"');
        console.warn('action URL   = "' + form.action + '"');

        // DEBUG MODE: hold for 10 seconds so you can read console, then submit
        var debugHold = {{ app()->isLocal() ? 'true' : 'false' }};
        var hasCreds  = fields.merchant_id && fields.merchant_key;

        if (!hasCreds) {
            console.error('[PayFast] MISSING CREDENTIALS — form will NOT submit. Check config/services.payfast');
            document.body.insertAdjacentHTML('afterbegin',
                '<div style="background:red;color:#fff;padding:16px;font-size:16px;z-index:9999;position:fixed;top:0;left:0;right:0">'
                + '⚠️ PayFast merchant_id or merchant_key is EMPTY. Check server .env / config cache.'
                + '</div>'
            );
            return; // stop — do not submit broken form
        }

        console.log('[PayFast] Credentials present — submitting...');
        form.submit();
    })();
</script>

