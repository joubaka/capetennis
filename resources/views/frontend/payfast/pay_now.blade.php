<form id="payfastForm" action="{{ $payfast->url }}" method="post">
    <input type="hidden" name="merchant_id" value="{{ $payfast->id }}">
    <input type="hidden" name="merchant_key" value="{{ $payfast->key }}">
    <input type="hidden" name="return_url" value="{{ $return_url }}">
    <input type="hidden" name="cancel_url" value="{{ $cancel_url }}">
    <input type="hidden" name="notify_url" value="{{ $notify_url }}">
    <input type="hidden" name="amount" value="{{ $amount }}">
    <input type="hidden" name="item_name" value="Event Registration">
    <input type="hidden" name="custom_int5" value="{{ $orderId }}">
    <input type="hidden" name="custom_wallet_reserved" value="{{ $custom_wallet_reserved }}">
</form>

<script>
    document.getElementById('payfastForm').submit();
</script>
