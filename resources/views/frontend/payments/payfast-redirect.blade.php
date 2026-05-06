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

  @php
    $formFields = array_filter([
      'merchant_id'  => $payfast->id,
      'merchant_key' => $payfast->key,
      'amount'       => number_format($payfast->amount, 2, '.', ''),
      'item_name'    => $payfast->item_name,
      'return_url'   => $payfast->return_url,
      'cancel_url'   => $payfast->cancel_url,
      'notify_url'   => $payfast->notify_url,
      'custom_int1'  => $payfast->custom_int1 ? (string)$payfast->custom_int1 : null,
      'custom_int2'  => $payfast->custom_int2 ? (string)$payfast->custom_int2 : null,
      'custom_int3'  => $payfast->custom_int3 ? (string)$payfast->custom_int3 : null,
      'custom_int4'  => $payfast->custom_int4 ? (string)$payfast->custom_int4 : null,
      'custom_int5'  => $payfast->custom_int5 ? (string)$payfast->custom_int5 : null,
    ], fn($v) => $v !== null && $v !== '');
  @endphp
  <input type="hidden" name="signature" value="{{ $payfast->generateFormSignature($formFields) }}"></form>

<script>
  document.getElementById('payfastForm').submit();
</script>
