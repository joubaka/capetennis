@unless($entrySummaryDetailsOnly ?? false)
<h2 @unless($entrySummaryPdf ?? false) class="h5" @endunless>Entry and withdrawal summary</h2>
@if($entrySummaryPdf ?? false)
<table class="entry-summary-totals"><tbody><tr>
@foreach(['recorded', 'confirmed', 'unpaid', 'withdrawn'] as $field)
<td><span class="muted">{{ $entrySummary['columns'][$field] }}</span><br><strong style="font-size:17px">{{ number_format($entrySummary['totals'][$field]) }}</strong></td>
@endforeach
</tr></tbody></table>
@else
<div class="row g-2 mb-3">
@foreach(['recorded', 'confirmed', 'unpaid', 'withdrawn'] as $field)
  <div class="col-6 col-md-3"><div class="border rounded p-2 h-100"><div class="small text-muted">{{ $entrySummary['columns'][$field] }}</div><strong class="fs-5">{{ number_format($entrySummary['totals'][$field]) }}</strong></div></div>
@endforeach
</div>
<p class="small text-muted d-md-none">Swipe the division tables to see all columns.</p>
<a class="btn btn-outline-secondary btn-sm mb-3" href="{{ route('admin.events.finances', ['event' => $event, 'withdrawals' => 1]) }}">View withdrawal records</a>
@endif
@endunless
@unless($entrySummaryTotalsOnly ?? false)
<p class="{{ ($entrySummaryPdf ?? false) ? 'muted' : 'text-muted small' }}">Counts are entry records, not unique players. Recorded entries include active confirmed, active unpaid and withdrawals. Confirmed includes free and administrator-confirmed entries. Unfinished checkouts and removed unpaid attempts are excluded; archived withdrawals remain included.</p>
<p class="{{ ($entrySummaryPdf ?? false) ? 'muted' : 'text-muted small' }}">Entries in payment records measure the financial ledger, including payment history. This participation summary measures current entries and recorded withdrawals. The two counts can differ and are not a reconciliation.</p>
@if($entrySummary['team'])
<p class="{{ ($entrySummaryPdf ?? false) ? 'muted' : 'text-muted small' }}">Team counts use current roster places and withdrawals of confirmed team-player registrations. Unpaid roster selections are shown separately; cancelled or expired unpaid orders are not withdrawals.</p>
@endif
@foreach([
  ['recorded', 'confirmed', 'unpaid', 'withdrawn'],
  ['withdrawn', 'completed', 'pending', 'waived', 'no_refund'],
] as $fields)
@php
  $isWithdrawalSummary = $loop->last;
  $summaryDivisions = collect($entrySummary['divisions'])->filter(fn ($division) => $isWithdrawalSummary ? $division['withdrawn'] > 0 : (!($entrySummaryPdf ?? false) || $division['recorded'] > 0));
@endphp
@if($loop->last)<h3 @unless($entrySummaryPdf ?? false) class="h6 mt-3" @endunless>Withdrawal refund status</h3>@endif
<div @unless($entrySummaryPdf ?? false) class="table-responsive" @endunless>
<table class="{{ ($entrySummaryPdf ?? false) ? 'entry-summary-table' : 'table table-sm align-middle mb-2' }}">
  <thead><tr><th>Division</th>@foreach($fields as $field)<th class="{{ ($entrySummaryPdf ?? false) ? 'amount' : 'text-end' }}">{{ $entrySummary['columns'][$field] }}</th>@endforeach</tr></thead>
  <tbody>
  @forelse($summaryDivisions as $division)
    <tr><td>{{ $division['name'] }}</td>@foreach($fields as $field)<td class="{{ ($entrySummaryPdf ?? false) ? 'amount' : 'text-end' }}">{{ number_format($division[$field]) }}</td>@endforeach</tr>
  @empty
    <tr><td colspan="{{ count($fields) + 1 }}">{{ $isWithdrawalSummary ? 'No withdrawals recorded.' : 'No entries recorded.' }}</td></tr>
  @endforelse
  <tr class="{{ ($entrySummaryPdf ?? false) ? 'total' : 'fw-bold' }}"><td>Event total</td>@foreach($fields as $field)<td class="{{ ($entrySummaryPdf ?? false) ? 'amount' : 'text-end' }}">{{ number_format($entrySummary['totals'][$field]) }}</td>@endforeach</tr>
  </tbody>
</table></div>
@endforeach
@endunless
