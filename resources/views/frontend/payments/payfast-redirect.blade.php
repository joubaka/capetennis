<form action="{{ $payfast->url }}" method="post" id="payfastForm">
  <input type="hidden" name="merchant_id" value="{{ $payfast->id }}">
  <input type="hidden" name="merchant_key" value="{{ $payfast->key }}">

  <input type="hidden" name="amount" value="{{ number_format($payfast->amount, 2, '.', '') }}">
  <input type="hidden" name="item_name" value="{{ $payfast->item_name }}">

  <input type="hidden" name="return_url" value="{{ $payfast->return_url }}">
  <input type="hidden" name="cancel_url" value="{{ $payfast->cancel_url }}">
  <input type="hidden" name="notify_url" value="{{ $payfast->notify_url }}">

  {{-- custom tracking --}}
  <input type="hidden" name="custom_int1" value="{{ $payfast->custom_int1 }}">
  <input type="hidden" name="custom_int2" value="{{ $payfast->custom_int2 }}">
  <input type="hidden" name="custom_int3" value="{{ $payfast->custom_int3 }}">
  <input type="hidden" name="custom_int4" value="{{ $payfast->custom_int4 }}">
  <input type="hidden" name="custom_int5" value="{{ $payfast->custom_int5 }}">
</form>

<script>
  document.getElementById('payfastForm').submit();
</script>
