<h2>Cape Tennis payment/registration failure</h2>
<p>A payment or registration operation failed. Reference: <strong>{{ $details['reference'] ?? 'unknown' }}</strong></p>
<table>
@foreach ($details as $key => $value)
    <tr><th style="text-align:left;padding-right:16px;vertical-align:top">{{ $key }}</th><td>{{ is_scalar($value) ? $value : json_encode($value, JSON_PRETTY_PRINT) }}</td></tr>
@endforeach
</table>
