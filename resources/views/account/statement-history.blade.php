<div class="statement-history-intro"><h2 class="statement-section-title">Historical paid records</h2>
<p class="statement-caption">Marked paid in Cape Tennis; full receipt evidence is unavailable. Undated records appear regardless of the selected payment period. Event dates are not payment dates.</p>
<div class="statement-fee-summary">
<p><strong>Recorded historical entry fees: R {{ number_format(($recordedFeeTotal ?? 0) / 100, 2) }}</strong> · Original captured item prices.</p>
<p><strong>Current listed fee references: R {{ number_format(($listedFeeTotal ?? 0) / 100, 2) }}</strong> · Category or event fees used only where captured prices are missing.</p>
<p>These two fee subtotals are separate from known payments. Current listings do not establish the price charged at the time. Player filters apply to both fee subtotals.</p>
</div></div>
<div class="table-responsive"><table class="statement-table statement-history-table">
<colgroup><col style="width:14%"><col style="width:19%"><col style="width:37%"><col style="width:15%"><col style="width:15%"></colgroup>
<thead><tr><th>Payment date</th><th>Record / reference</th><th>Player / event / fee source</th><th class="statement-right">Payment amount</th><th class="statement-right">Entry fee reference</th></tr></thead>
<tbody>@forelse($historyRows as $row)<tr>
<td>@if($row['date']){{ substr($row['date'], 0, 10) }}@else<span class="statement-unavailable">Payment date unavailable</span>@endif</td>
<td><strong>{{ $row['checkout'] }}</strong><br><span class="statement-reference">{{ $row['reference'] }}</span><br><small>{{ $row['method'] }}</small></td>
<td>@foreach($row['details'] as $detail)<div class="entry-detail"><strong>{{ $detail['player'] }}</strong><br>{{ $detail['event'] }}
@if($detail['event_date'])<br><small>Event date: {{ \Illuminate\Support\Carbon::parse($detail['event_date'])->format('d M Y') }}</small>@endif
@if(($detail['recorded_fee'] ?? null) !== null)<span class="statement-fee-source">Recorded historical entry fee: R {{ number_format($detail['recorded_fee'] / 100, 2) }}<br>Source: original registration item</span>
@elseif(($detail['listed_fee'] ?? null) !== null)<span class="statement-fee-source">{{ $detail['listed_fee_source'] }}: R {{ number_format($detail['listed_fee'] / 100, 2) }}<br>Reference only; historical price unverified</span>@endif
</div>@endforeach</td>
<td class="statement-money">@if($row['amount'] === null)<span class="statement-unavailable">Amount unavailable</span>@else R {{ number_format($row['amount'] / 100, 2) }}@endif</td>
<td class="statement-money">
@if(($row['recordedFee'] ?? null) !== null)R {{ number_format($row['recordedFee'] / 100, 2) }}<span class="statement-fee-source">{{ ($row['partialRecordedFee'] ?? false) ? 'Partial captured fees' : 'Captured fees' }}</span>@endif
@if(($row['listedFee'] ?? null) !== null)@if(($row['recordedFee'] ?? null) !== null)<br>@endif R {{ number_format($row['listedFee'] / 100, 2) }}<span class="statement-fee-source">Current listed fees</span>@endif
@if(($row['recordedFee'] ?? null) === null && ($row['listedFee'] ?? null) === null)<span class="statement-unavailable">Amount unavailable</span>@endif
</td></tr>@empty<tr><td colspan="5" class="statement-empty">No additional historical paid records.</td></tr>@endforelse</tbody></table></div>
