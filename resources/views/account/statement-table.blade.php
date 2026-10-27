<table class="statement-table statement-transactions">
<colgroup><col style="width:14%"><col style="width:20%"><col style="width:40%"><col style="width:13%"><col style="width:13%"></colgroup>
<thead><tr><th>Recorded date</th><th>Payment / reference</th><th>Player / transaction details</th><th class="statement-right">Paid (R)</th><th class="statement-right">Refunded (R)</th></tr></thead>
<tbody>@forelse($statementRows as $row)<tr>
<td>{{ substr($row['date'], 0, 10) }}<br><small>{{ substr($row['date'], 11, 8) }}</small></td>
<td><strong>{{ $row['method'] }}</strong><br><span class="statement-reference">{{ $row['reference'] }}</span><br><small>{{ $row['checkout'] }}</small></td>
<td>@foreach($row['details'] as $detail)<div class="entry-detail"><strong>{{ $detail['player'] }}</strong><br>{{ $detail['event'] }}
@if($detail['event_date'])<br><small>Event: {{ \Illuminate\Support\Carbon::parse($detail['event_date'])->format('d M Y') }}</small>@endif
@if($detail['fee'] !== null)<br><small>Recorded checkout item fee: R {{ number_format($detail['fee'] / 100, 2) }}</small>@endif
</div>@endforeach</td>
<td class="statement-money">{{ number_format($row['paid'] / 100, 2) }}</td><td class="statement-money">{{ number_format($row['refunded'] / 100, 2) }}</td>
</tr>@empty<tr><td colspan="5" class="statement-empty">No recorded entry payments or completed refunds match this period and player filter.</td></tr>@endforelse</tbody></table>
