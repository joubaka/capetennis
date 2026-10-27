<h2 class="h5">Historical paid records</h2>
<p>These records are marked paid in Cape Tennis, but complete payment evidence is unavailable. They are shown separately and excluded from known receipt totals. Undated records are included regardless of the selected payment period; event dates are not payment dates.</p>
<p><strong>Recorded historical entry fees: R {{ number_format(($recordedFeeTotal ?? 0) / 100, 2) }}</strong>. These are original captured entry fees, not verified payment amounts, and are excluded from known receipt totals. Missing or ambiguous fees remain unavailable. Player filters limit this fee subtotal to matching historical players.</p>
<table class="table statement-table"><thead><tr><th>Payment date</th><th>Record / reference</th><th>Player / event</th><th class="text-end">Recorded amount</th></tr></thead><tbody>
@forelse($historyRows as $row)
<tr><td>{{ $row['date'] ?? 'Payment date unavailable' }}</td><td>{{ $row['checkout'] }}<br>{{ $row['reference'] }}<br>{{ $row['method'] }}</td><td>@foreach($row['details'] as $detail)<div class="entry-detail"><strong>{{ $detail['player'] }}</strong><br>{{ $detail['event'] }}@if($detail['event_date'])<br><small>Event date: {{ \Illuminate\Support\Carbon::parse($detail['event_date'])->format('Y-m-d') }}</small>@endif
@if(($detail['recorded_fee'] ?? null) !== null)<br><small>Recorded historical entry fee: R {{ number_format($detail['recorded_fee'] / 100, 2) }}</small>@endif
</div>@endforeach</td><td class="text-end">{{ $row['amount'] === null ? 'Amount unavailable' : 'R '.number_format($row['amount'] / 100, 2) }}
@if(($row['recordedFee'] ?? null) !== null)<br><small>{{ ($row['partialRecordedFee'] ?? false) ? 'Partial recorded historical entry fee' : 'Recorded historical entry fee' }}: R {{ number_format($row['recordedFee'] / 100, 2) }}</small>@endif
</td></tr>
@empty<tr><td colspan="4">No additional historical paid records.</td></tr>@endforelse
</tbody></table>
