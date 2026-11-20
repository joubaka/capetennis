@php($rand = fn($cents) => 'R'.number_format($cents / 100, 2))
<section class="card mb-4" data-financial-projection aria-label="Projected event finances">
<div class="card-body">
<h2 class="h5">Your projected event finances</h2>
<p class="small text-muted">Assumes every place in your entry limits is paid. Processing is fixed at 5% and Cape Tennis at R15 per entry. Rental covers assigned courts only.</p>
<div data-projection-unavailable role="status" @if($projection) hidden @endif>Enter valid courts, entry limits, fees and costs to see an updated projection.</div>
<div data-projection-values @if(!$projection) hidden @endif>
<div class="table-responsive"><table class="table"><thead><tr><th>Category</th><th>Entries</th><th>Entry fee</th><th>Collected</th><th>Processing</th><th>Cape Tennis</th><th>Retained</th></tr></thead><tbody>
@foreach(\App\Services\Marketing\PanarottisCalculator::CATEGORIES as $i => $category)
<tr><th scope="row">{{ $category }}</th>@foreach(['entries', 'fee', 'gross', 'processing', 'platform', 'retained'] as $field)<td class="text-nowrap" data-projection-cell="{{ $i }}-{{ $field }}">{{ $projection ? ($field === 'entries' ? $projection['rows'][$i][$field] : $rand($projection['rows'][$i][$field])) : '—' }}</td>@endforeach</tr>
@endforeach
<tr class="fw-bold"><th>Total</th><td data-projection-total="entries">{{ $projection['totals']['entries'] ?? '—' }}</td><td>—</td>@foreach(['gross', 'processing', 'platform', 'retained'] as $field)<td class="text-nowrap" data-projection-total="{{ $field }}">{{ $projection ? $rand($projection['totals'][$field]) : '—' }}</td>@endforeach</tr>
</tbody></table></div>
<dl class="row mt-3">
<dt class="col-7">Convenor</dt><dd class="col-5 text-end" data-projection-total="convenor">{{ $projection ? $rand($projection['totals']['convenor']) : '—' }}</dd>
<dt class="col-7">Assigned-court rental</dt><dd class="col-5 text-end" data-projection-total="rental">{{ $projection ? $rand($projection['totals']['rental']) : '—' }}</dd>
<dt class="col-7">Other expenses</dt><dd class="col-5 text-end" data-projection-total="other_costs">{{ $projection ? $rand($projection['totals']['other_costs']) : '—' }}</dd>
</dl>
<p class="small text-muted" data-projection-rental-formula>@if($projection){{ $projection['assumptions']['courts'] }} assigned courts × {{ $projection['assumptions']['hours'] }} hours × R{{ $projection['assumptions']['rental'] }} per court/hour.@endif</p>
<div class="p-3 rounded {{ $projection && $projection['totals']['remaining'] < 0 ? 'bg-label-danger' : 'bg-label-primary' }}" data-projection-result>
<div class="fw-semibold" data-projection-result-label>{{ $projection && $projection['totals']['remaining'] < 0 ? 'Projected loss' : 'Academy has left' }}</div>
<div class="fs-3 fw-bold" data-projection-total="remaining">{{ $projection ? $rand($projection['totals']['remaining']) : '—' }}</div>
</div>
</div>
<p class="small text-muted mt-3 mb-0">Planning only, before any expenses you have not included. The 5% is a processing allowance, not a verified provider tariff; actual schedule and rental arrangements still need review.</p>
</div></section>
